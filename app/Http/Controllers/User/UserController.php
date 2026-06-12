<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Media;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Channels\EvolutionApiClient;
use Modules\ChatBot\Models\Conversation;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = $this->buildUsersQuery($request)
            ->paginate(10)
            ->through(fn (User $u): array => $this->userToArray($u));

        return Inertia::render('admin/usuarios/index', [
            'users' => $users,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'country_code' => (string) $request->input('country_code', ''),
                'is_whatsapp_business' => (string) $request->input('is_whatsapp_business', ''),
                'role' => (string) $request->input('role', ''),
                'verified' => (string) $request->input('verified', ''),
            ],
            'availableCountries' => $this->availableCountries(),
            'availableRoles' => Role::query()->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = (int) $request->integer('per_page', 10);
        $page = (int) $request->integer('page', 1);

        $users = $this->buildUsersQuery($request)
            ->paginate(min($perPage, 50), ['*'], 'page', $page)
            ->through(fn (User $u): array => $this->userToArray($u));

        return response()->json([
            'data' => $users->items(),
            'total' => $users->total(),
            'per_page' => $users->perPage(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ]);
    }

    /**
     * Construye el query base con todos los filtros (search, country, business, role, verified).
     * Reutilizado por index() (HTML) y search() (JSON).
     */
    private function buildUsersQuery(Request $request): Builder
    {
        return User::query()
            ->with('roles:id,name')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->input('search');
                $digits = preg_replace('/\D+/', '', $search) ?? '';
                $query->where(function ($q) use ($search, $digits): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$digits}%")
                        ->orWhere('whatsapp_jid', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('country_code'), function ($query) use ($request): void {
                $code = strtoupper((string) $request->input('country_code'));
                $query->where('country_code', $code);
            })
            ->when($request->filled('is_whatsapp_business'), function ($query) use ($request): void {
                $val = $request->input('is_whatsapp_business');
                if ($val === '1' || $val === 'true') {
                    $query->where('is_whatsapp_business', true);
                } elseif ($val === '0' || $val === 'false') {
                    $query->where(function ($q): void {
                        $q->whereNull('is_whatsapp_business')->orWhere('is_whatsapp_business', false);
                    });
                }
            })
            ->when($request->filled('role'), function ($query) use ($request): void {
                $role = (string) $request->input('role');
                $query->whereHas('roles', function ($q) use ($role): void {
                    $q->where('name', $role);
                });
            })
            ->when($request->filled('verified'), function ($query) use ($request): void {
                $val = $request->input('verified');
                if ($val === '1' || $val === 'true') {
                    $query->whereNotNull('email_verified_at');
                } elseif ($val === '0' || $val === 'false') {
                    $query->whereNull('email_verified_at');
                }
            })
            ->latest();
    }

    /**
     * Lista de códigos de país (ISO-3166-alpha2) que tienen al menos un usuario,
     * ordenada alfabéticamente. Sin países hardcodeados: se deriva de la BD.
     *
     * @return list<string>
     */
    private function availableCountries(): array
    {
        return User::query()
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code')
            ->map(fn ($c) => strtoupper((string) $c))
            ->all();
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/usuarios/create', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $this->syncDefaultRole($user, $data['roles'] ?? []);

            return $user;
        });

        return to_route('admin.usuarios.index')
            ->with('success', "Usuario {$user->name} creado.");
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);
        $user->load('avatar');

        return Inertia::render('admin/usuarios/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country_code' => $user->country_code,
                'whatsapp_jid' => $user->whatsapp_jid,
                'is_whatsapp_business' => (bool) $user->is_whatsapp_business,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'avatar_url' => $user->avatar?->url(),
                'avatar_media_id' => $user->avatar_media_id,
                'roles' => $user->roles->pluck('name')->all(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->name = $data['name'];
            $user->email = $data['email'];

            if (array_key_exists('phone', $data) && $data['phone'] !== null) {
                $user->phone = preg_replace('/\D+/', '', (string) $data['phone']) ?: null;
            }

            if (array_key_exists('whatsapp_jid', $data)) {
                $user->whatsapp_jid = $data['whatsapp_jid'] !== null && $data['whatsapp_jid'] !== ''
                    ? (string) $data['whatsapp_jid']
                    : null;
            }

            if (array_key_exists('is_whatsapp_business', $data)) {
                $user->is_whatsapp_business = (bool) $data['is_whatsapp_business'];
            }

            if (array_key_exists('email_verified', $data)) {
                if ((bool) $data['email_verified'] && $user->email_verified_at === null) {
                    $user->email_verified_at = now();
                } elseif (! (bool) $data['email_verified']) {
                    $user->email_verified_at = null;
                }
            }

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $this->syncDefaultRole($user, $data['roles'] ?? []);
        });

        return to_route('admin.usuarios.index')
            ->with('success', "Usuario {$user->name} actualizado.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->delete();
        });

        return to_route('admin.usuarios.index')
            ->with('success', 'Usuario eliminado.');
    }

    public function withEvolutionCheck(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:5'],
        ]);

        $serverUrl = (string) config('services.evolution.server_url', env('EVOLUTION_DEFAULT_SERVER_URL'));
        $apiKey = (string) config('services.evolution.api_key', env('EVOLUTION_DEFAULT_API_KEY'));

        if ($serverUrl === '' || $apiKey === '') {
            return response()->json([
                'ok' => false,
                'exists' => false,
                'error' => 'Evolution no está configurado.',
            ], 503);
        }

        $data = $this->findBestEvolutionData($validated['phone'], $serverUrl, $apiKey, null);

        if (! ($data['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'exists' => false,
                'error' => 'No se pudo conectar con Evolution.',
            ], 502);
        }

        $avatarMediaId = null;
        if (! empty($data['profile_picture_url'])) {
            $avatarMediaId = self::downloadAvatar(
                (string) $data['profile_picture_url'],
                (string) ($data['number'] ?? $validated['phone']),
                null,
            );
        }

        return response()->json([
            'ok' => true,
            'exists' => (bool) $data['exists'],
            'jid' => $data['jid'] ?? null,
            'number' => $data['number'] ?? null,
            'push_name' => $data['push_name'] ?? null,
            'profile_picture_url' => $data['profile_picture_url'] ?? null,
            'avatar_media_id' => $avatarMediaId,
            'is_business' => (bool) ($data['is_business'] ?? false),
            'business_data' => $data['business_data'] ?? null,
        ]);
    }

    public function createWithEvolution(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'phone' => ['required', 'string', 'min:5'],
            'push_name' => ['nullable', 'string', 'max:255'],
            'profile_picture_url' => ['nullable', 'string', 'max:2048'],
            'avatar_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'whatsapp_jid' => ['nullable', 'string', 'max:255'],
            'is_business' => ['nullable', 'boolean'],
            'business_data' => ['nullable', 'array'],
        ]);

        $normalized = preg_replace('/\D+/', '', (string) $data['phone']) ?? '';
        $pushName = trim((string) ($data['push_name'] ?? ''));
        if ($pushName === '' || self::isGenericPushName($pushName, $normalized)) {
            $pushName = 'Usuario '.$normalized;
        }

        $email = 'wa+'.$normalized.'@hostbol.lat';

        $user = DB::transaction(function () use ($data, $email, $pushName, $normalized) {
            $existing = User::where('email', $email)->first();
            if ($existing) {
                $user = $existing;
            } else {
                $user = User::create([
                    'name' => $pushName,
                    'email' => $email,
                    'phone' => $normalized,
                    'password' => Hash::make(Str::random(32)),
                ]);

                $this->syncDefaultRole($user, []);
            }

            $user->phone = $normalized;
            $user->whatsapp_jid = $data['whatsapp_jid'] ?? $user->whatsapp_jid;
            $user->is_whatsapp_business = (bool) ($data['is_business'] ?? false);
            $user->business_data = $data['business_data'] ?? null;

            if (! empty($data['avatar_media_id'])) {
                $user->avatar_media_id = (int) $data['avatar_media_id'];
            } elseif (! empty($data['profile_picture_url'])) {
                $mediaId = self::downloadAvatar((string) $data['profile_picture_url'], $normalized, $user->id);
                if ($mediaId) {
                    $user->avatar_media_id = $mediaId;
                }
            }

            $user->save();

            if (preg_match('/^wa\+\d+@hostbol\.lat$/', (string) $user->email)) {
                $slug = Str::slug($user->name);
                if ($slug !== '') {
                    $user->email = $slug.'@hostbol.lat';
                    $user->save();
                }
            }

            return $user;
        });

        return to_route('admin.usuarios.index')
            ->with('success', "Usuario {$user->name} creado desde Evolution.");
    }

    public function saveEvolutionData(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'phone' => ['required', 'string', 'min:5'],
            'push_name' => ['nullable', 'string', 'max:255'],
            'profile_picture_url' => ['nullable', 'string', 'max:2048'],
            'avatar_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'whatsapp_jid' => ['nullable', 'string', 'max:255'],
            'is_business' => ['nullable', 'boolean'],
            'business_data' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $normalized = preg_replace('/\D+/', '', (string) $data['phone']) ?? '';
            $pushName = trim((string) ($data['push_name'] ?? ''));

            if ($pushName !== '' && ! self::isGenericPushName($pushName, $normalized)) {
                $user->name = $pushName;
            }

            if (preg_match('/^wa\+\d+@hostbol\.lat$/', (string) $user->email)) {
                $slug = Str::slug($user->name);
                if ($slug !== '') {
                    $user->email = $slug.'@hostbol.lat';
                }
            }

            $user->phone = $normalized;
            $user->whatsapp_jid = $data['whatsapp_jid'] ?? $user->whatsapp_jid;
            $user->is_whatsapp_business = (bool) ($data['is_business'] ?? false);
            $user->business_data = $data['business_data'] ?? null;

            if (! empty($data['avatar_media_id'])) {
                $user->avatar_media_id = (int) $data['avatar_media_id'];
            } elseif (! empty($data['profile_picture_url'])) {
                $mediaId = self::downloadAvatar((string) $data['profile_picture_url'], $normalized, $user->id);
                if ($mediaId) {
                    $user->avatar_media_id = $mediaId;
                }
            }

            $user->save();
        });

        return to_route('admin.usuarios.index')
            ->with('success', "Datos de Evolution guardados para {$user->name}.");
    }

    public function checkWhatsapp(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:5'],
        ]);

        $serverUrl = (string) config('services.evolution.server_url', env('EVOLUTION_DEFAULT_SERVER_URL'));
        $apiKey = (string) config('services.evolution.api_key', env('EVOLUTION_DEFAULT_API_KEY'));

        if ($serverUrl === '' || $apiKey === '') {
            return response()->json([
                'ok' => false,
                'exists' => false,
                'error' => 'Evolution no está configurado.',
            ], 503);
        }

        $data = $this->findBestEvolutionData($validated['phone'], $serverUrl, $apiKey, $user->id);

        if (! ($data['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'exists' => false,
                'error' => 'No se pudo conectar con Evolution.',
            ], 502);
        }

        $avatarMediaId = null;
        if (! empty($data['profile_picture_url'])) {
            $avatarMediaId = self::downloadAvatar(
                (string) $data['profile_picture_url'],
                (string) ($data['number'] ?? $validated['phone']),
                $user->id,
            );
        }

        return response()->json([
            'ok' => true,
            'exists' => (bool) $data['exists'],
            'jid' => $data['jid'] ?? null,
            'number' => $data['number'] ?? null,
            'push_name' => $data['push_name'] ?? null,
            'profile_picture_url' => $data['profile_picture_url'] ?? null,
            'avatar_media_id' => $avatarMediaId,
            'is_business' => (bool) ($data['is_business'] ?? false),
            'business_data' => $data['business_data'] ?? null,
        ]);
    }

    private function userToArray(User $user): array
    {
        $chatConversationId = null;
        if ($user->phone) {
            $chatConversationId = Conversation::where('user_id', $user->id)
                ->whereNotNull('external_id')
                ->value('id');
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'is_whatsapp_business' => (bool) $user->is_whatsapp_business,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'avatar_url' => $user->avatar?->url(),
            'roles' => $user->roles->pluck('name')->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
            'chat_conversation_id' => $chatConversationId,
        ];
    }

    private function syncDefaultRole(User $user, array $roles): void
    {
        $roles = array_values(array_filter($roles, fn ($r) => is_string($r) && $r !== ''));

        if ($roles === []) {
            $defaultRole = Role::where('name', 'user')->first() ?? Role::orderBy('id')->first();
            if ($defaultRole) {
                $roles = [$defaultRole->name];
            }
        }

        $user->syncRoles($roles);
    }

    private function findBestEvolutionData(string $phone, string $serverUrl, string $apiKey, ?int $userId): array
    {
        $instancesResp = Http::withHeaders([
            'apikey' => $apiKey,
        ])->get(rtrim($serverUrl, '/').'/instance/fetchInstances');

        if (! $instancesResp->successful()) {
            return [
                'ok' => false,
                'exists' => false,
                'jid' => null,
                'number' => '',
                'push_name' => null,
                'profile_picture_url' => null,
                'avatar_media_id' => null,
                'is_business' => false,
                'business_data' => null,
            ];
        }

        $allInstances = $instancesResp->json();
        $instanceNames = [];
        if (is_array($allInstances)) {
            foreach ($allInstances as $inst) {
                $name = $inst['name'] ?? null;
                if ($name && ! in_array($name, $instanceNames, true)) {
                    $instanceNames[] = $name;
                }
            }
        }

        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        $exists = false;
        $jid = null;
        $number = $normalized;
        $pushName = null;
        $profilePictureUrl = null;
        $isBusiness = false;
        $businessData = null;

        foreach ($instanceNames as $instanceName) {
            $client = new EvolutionApiClient(
                serverUrl: $serverUrl,
                apiKey: $apiKey,
                instanceName: $instanceName,
            );

            try {
                $checkResp = $client->checkWhatsappNumbers([$normalized]);
            } catch (\Throwable) {
                continue;
            }

            if (! $checkResp->successful()) {
                continue;
            }

            $checkData = $checkResp->json();
            $first = is_array($checkData) && isset($checkData[0]) ? $checkData[0] : null;
            $instExists = (bool) ($first['exists'] ?? false);

            if (! $instExists) {
                continue;
            }

            if (! $exists) {
                $exists = true;
                $jid = $first['jid'] ?? null;
                $number = $first['number'] ?? $normalized;
            }

            try {
                $profileResp = $client->fetchProfile($number);
                if ($profileResp->successful()) {
                    $profileData = $profileResp->json();
                    $candidate = null;
                    if (is_array($profileData) && isset($profileData[0])) {
                        $candidate = $profileData[0]['pushName'] ?? $profileData[0]['name'] ?? null;
                        $isBusiness = (bool) ($profileData[0]['isBusiness'] ?? false);
                        if (! $jid && isset($profileData[0]['wuid'])) {
                            $jid = $profileData[0]['wuid'];
                        }
                    } else {
                        $candidate = $profileData['pushName'] ?? $profileData['name'] ?? null;
                        $isBusiness = (bool) ($profileData['isBusiness'] ?? false);
                        if (! $jid && isset($profileData['wuid'])) {
                            $jid = $profileData['wuid'];
                        }
                    }
                    if ($candidate && ! self::isGenericPushName($candidate, $number)) {
                        $pushName = $candidate;
                    }
                }
            } catch (\Throwable) {
            }

            try {
                $picResp = $client->fetchProfilePictureUrl($number);
                if ($picResp->successful()) {
                    $picData = $picResp->json();
                    $url = $picData['profilePictureUrl'] ?? null;
                    if ($url && ! $profilePictureUrl) {
                        $profilePictureUrl = $url;
                    }
                }
            } catch (\Throwable) {
            }

            if ($isBusiness) {
                try {
                    $bizResp = $client->fetchBusinessProfile($number);
                    if ($bizResp->successful()) {
                        $bizData = $bizResp->json();
                        $bizIsBusiness = (bool) ($bizData['isBusiness'] ?? false);
                        if ($bizIsBusiness) {
                            $website = $bizData['website'] ?? null;
                            $businessData = [
                                'description' => $bizData['description'] ?? null,
                                'website' => is_array($website) ? ($website[0] ?? null) : $website,
                                'category' => $bizData['category'] ?? null,
                                'business_hours' => $bizData['business_hours'] ?? null,
                            ];
                        }
                    }
                } catch (\Throwable) {
                }
            }

            $businessComplete = ! $isBusiness
                || ($businessData && ($businessData['description'] ?? null) && ($businessData['website'] ?? null) && ($businessData['category'] ?? null));

            if ($pushName && $profilePictureUrl && $businessComplete) {
                break;
            }
        }

        return [
            'ok' => true,
            'exists' => $exists,
            'jid' => $jid,
            'number' => $number,
            'push_name' => $pushName,
            'profile_picture_url' => $profilePictureUrl,
            'avatar_media_id' => null,
            'is_business' => $isBusiness,
            'business_data' => $businessData,
        ];
    }

    private static function isGenericPushName(?string $name, string $phone): bool
    {
        if (! $name) {
            return true;
        }
        $trimmed = trim($name);
        if ($trimmed === '') {
            return true;
        }
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';
        $normalizedName = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($normalizedName !== '' && $normalizedName === $normalizedPhone) {
            return true;
        }
        if (str_starts_with(strtolower($trimmed), 'usuario')) {
            return true;
        }

        return false;
    }

    private static function downloadAvatar(string $url, string $phone, ?int $userId): ?int
    {
        try {
            $download = Http::timeout(30)->get($url);
            if (! $download->successful()) {
                return null;
            }
            $body = $download->body();
            $mime = $download->header('Content-Type') ?: 'image/jpeg';
            $ext = match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'gif') => 'gif',
                default => 'jpg',
            };
            $normalized = preg_replace('/\D+/', '', $phone) ?? 'user';
            $originalName = 'whatsapp-avatar-'.$normalized.'-'.time().'.'.$ext;
            $stored = app(MediaStorage::class)->storeBytes($body, $mime, $originalName);
            $media = Media::create([
                'disk' => config('media.disk'),
                'path' => $stored['path'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'name' => $stored['name'],
                'user_id' => $userId,
            ]);

            return $media->id;
        } catch (\Throwable $e) {
            Log::warning('Failed to download WhatsApp avatar', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
