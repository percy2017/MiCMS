<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchLinkPreviewsJob;
use App\Models\Media;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Http\Requests\ReplyMessageRequest;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Services\ChannelManager;
use Modules\ChatBot\Services\ChatBotMessageService;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatBotMessageService $service,
        private readonly ChannelManager $channelManager,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view chats'), 403);

        $conversations = $this->listConversations($request);

        $channels = Channel::query()
            ->where('enabled', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (Channel $ch): array => [
                'id' => $ch->id,
                'name' => $ch->type?->value === 'evolution'
                    ? ($ch->config['instance_name'] ?? $ch->settings['display_name'] ?? $ch->name)
                    : ($ch->settings['display_name'] ?? $ch->name),
                'type' => $ch->type->value,
            ]);

        $stats = [
            'open' => Conversation::where('status', 'open')->count(),
            'unread' => Conversation::where('unread_by_admin', '>', 0)->count(),
            'total' => Conversation::count(),
        ];

        $routeConversation = $request->route('conversation');
        $activeId = $routeConversation
            ? (int) $routeConversation
            : ($request->integer('active') ?: null);
        $active = null;
        if ($activeId) {
            $conv = Conversation::with(['user:id,name,email,phone,whatsapp_jid,avatar_media_id', 'user.avatar:id,disk,path', 'channel:id,name,type,settings,config', 'messages.attachment'])->find($activeId);
            if ($conv) {
                $active = [
                    'id' => $conv->id,
                    'name' => $conv->user?->name ?? $conv->visitor_name,
                    'email' => $conv->user?->email,
                    'page_url' => $conv->page_url,
                    'status' => $conv->status,
                    'user_id' => $conv->user_id,
                    'user_avatar_url' => $conv->user?->avatar?->url(),
                    'user_phone' => $conv->user?->phone,
                    'user_whatsapp_jid' => $conv->user?->whatsapp_jid,
                    'external_id' => $conv->external_id,
                    'channel_id' => $conv->channel_id,
                    'channel_name' => $conv->channel
                        ? ($conv->channel->type?->value === 'evolution'
                            ? ($conv->channel->config['instance_name'] ?? $conv->channel->settings['display_name'] ?? $conv->channel->name)
                            : ($conv->channel->settings['display_name'] ?? $conv->channel->name))
                        : null,
                    'last_message_at' => $conv->last_message_at?->toIso8601String(),
                    'first_message_at' => $conv->messages->min('created_at')?->toIso8601String(),
                    'messages_count' => $conv->messages->count(),
                    'messages' => $conv->messages->map(fn (Message $m): array => [
                        'id' => $m->id,
                        'role' => $m->role,
                        'type' => $m->type->value,
                        'content' => $m->content,
                        ...$this->attachmentData($m),
                        'read_at' => $m->read_at?->toIso8601String(),
                        'created_at' => $m->created_at?->toIso8601String(),
                        'link_previews' => $m->link_previews,
                        'reactions' => $m->reactions()
                            ->get(['id', 'user_jid', 'emoji', 'created_at'])
                            ->map(fn ($r) => [
                                'id' => $r->id,
                                'user_jid' => $r->user_jid,
                                'emoji' => $r->emoji,
                                'created_at' => $r->created_at?->toIso8601String(),
                            ])->all(),
                    ])->values()->all(),
                ];

                $this->dispatchMissingLinkPreviews($conv);
            }
        }

        return Inertia::render('ChatBot::Chats/Index', [
            'conversations' => ['data' => $conversations],
            'stats' => $stats,
            'channels' => $channels,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => $request->input('status'),
                'channel_id' => $request->integer('channel_id') ?: null,
            ],
            'active' => $active,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view chats'), 403);

        $conversations = $this->listConversations($request);

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Reusable query to list conversations with filters for the admin UI.
     *
     * @return array<int, array<string, mixed>>
     */
    private function listConversations(Request $request): array
    {
        return Conversation::query()
            ->with(['user:id,name,email,phone,whatsapp_jid,avatar_media_id', 'user.avatar:id,disk,path', 'assignedAdmin:id,name', 'channel:id,name,type,settings,config'])
            ->with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', (string) $request->input('status'));
            })
            ->when($request->filled('channel_id'), function ($query) use ($request): void {
                $query->where('channel_id', (int) $request->input('channel_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->input('search');
                $query->where(function ($q) use ($search): void {
                    $q->whereHas('user', function ($uq) use ($search): void {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('whatsapp_jid', 'like', "%{$search}%");
                    })->orWhere('external_id', 'like', "%{$search}%")
                        ->orWhere('visitor_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Conversation $c): array => [
                'id' => $c->id,
                'name' => $c->user?->name ?? $c->visitor_name,
                'email' => $c->user?->email,
                'visitor_phone' => $c->user?->phone
                    ?? ($c->user?->whatsapp_jid ? preg_replace('/@.+$/', '', $c->user->whatsapp_jid) : null)
                    ?? $c->external_id,
                'status' => $c->status,
                'unread_by_admin' => $c->unread_by_admin,
                'messages_count' => $c->messages()->count(),
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'last_message_at_diff' => $c->last_message_at?->diffForHumans(),
                'last_message_preview' => $this->previewFor($c->messages->sortByDesc('created_at')->first()),
                'channel_id' => $c->channel_id,
                'channel_name' => $c->channel
                    ? ($c->channel->type?->value === 'evolution'
                        ? ($c->channel->config['instance_name'] ?? $c->channel->settings['display_name'] ?? $c->channel->name)
                        : ($c->channel->settings['display_name'] ?? $c->channel->name))
                    : null,
                'user' => $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'email' => $c->user->email,
                    'avatar_url' => $c->user->avatar?->url(),
                ] : null,
            ])
            ->values()
            ->all();
    }

    public function show(Conversation $conversation)
    {
        abort_unless(request()->user()?->can('view chats'), 403);

        $conversation->load(['user', 'channel:id,name,type,settings,config', 'messages.attachment']);

        $this->dispatchMissingLinkPreviews($conversation);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'name' => $conversation->user?->name ?? $conversation->visitor_name,
                'email' => $conversation->user?->email,
                'page_url' => $conversation->page_url,
                'status' => $conversation->status,
                'user_id' => $conversation->user_id,
                'channel_id' => $conversation->channel_id,
                'channel_name' => $conversation->channel
                    ? ($conversation->channel->type?->value === 'evolution'
                        ? ($conversation->channel->config['instance_name'] ?? $conversation->channel->settings['display_name'] ?? $conversation->channel->name)
                        : ($conversation->channel->settings['display_name'] ?? $conversation->channel->name))
                    : null,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'messages' => $conversation->messages->map(fn (Message $m): array => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'type' => $m->type->value,
                    'content' => $m->content,
                    ...$this->attachmentData($m),
                    'read_at' => $m->read_at?->toIso8601String(),
                    'created_at' => $m->created_at?->toIso8601String(),
                    'link_previews' => $m->link_previews,
                    'reactions' => $m->reactions()->get(['id', 'user_jid', 'emoji', 'created_at'])->map(fn ($r) => [
                        'id' => $r->id,
                        'user_jid' => $r->user_jid,
                        'emoji' => $r->emoji,
                        'created_at' => $r->created_at?->toIso8601String(),
                    ])->all(),
                ])->values()->all(),
            ],
        ]);
    }

    public function reply(ReplyMessageRequest $request, Conversation $conversation): JsonResponse
    {
        abort_unless($request->user()?->can('reply chatbot'), 403);

        $content = (string) ($request->input('content') ?? '');
        $hasFile = $request->hasFile('file');
        $providedMediaId = $request->integer('attachment_media_id') ?: null;

        if (! $hasFile && ! $providedMediaId && trim($content) === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Debes escribir un mensaje o adjuntar un archivo.',
            ], 422);
        }

        $mediaRecord = null;

        if ($hasFile) {
            $mediaRecord = $this->storeReplyFile($request->file('file'), $request->user()->id);
            $attachmentMediaId = $mediaRecord->id;
        } else {
            $attachmentMediaId = $providedMediaId;
        }

        $message = $this->service->buildAdminMessage(
            $conversation,
            $content,
            $attachmentMediaId,
        );

        try {
            $dispatchResult = $this->channelManager->dispatch($conversation, $message);
        } catch (\Throwable $e) {
            $this->cleanupOrphanMedia($mediaRecord, $attachmentMediaId);

            Log::error('Admin reply dispatch exception', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Error al enviar a WhatsApp: '.$e->getMessage(),
            ], 502);
        }

        if (! ($dispatchResult['ok'] ?? false)) {
            $this->cleanupOrphanMedia($mediaRecord, $attachmentMediaId);

            $errorMessage = $this->extractProviderError($dispatchResult['error'] ?? null)
                ?? 'No se pudo enviar el mensaje a WhatsApp.';

            return response()->json([
                'ok' => false,
                'error' => $errorMessage,
                'error_detail' => $dispatchResult['raw'] ?? null,
            ], 502);
        }

        $this->service->persistAdminMessage($message, $dispatchResult['provider_id'] ?? null);

        $conversation->update([
            'assigned_to' => $conversation->assigned_to ?? $request->user()->id,
        ]);

        $conversation->load([
            'user:id,name,email,phone,whatsapp_jid,avatar_media_id',
            'user.avatar:id,disk,path',
            'channel:id,name,type,settings,config',
            'messages.attachment',
        ]);

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'name' => $conversation->user?->name ?? $conversation->visitor_name,
                'email' => $conversation->user?->email,
                'page_url' => $conversation->page_url,
                'status' => $conversation->status,
                'user_id' => $conversation->user_id,
                'user_avatar_url' => $conversation->user?->avatar?->url(),
                'user_phone' => $conversation->user?->phone,
                'user_whatsapp_jid' => $conversation->user?->whatsapp_jid,
                'external_id' => $conversation->external_id,
                'channel_id' => $conversation->channel_id,
                'channel_name' => $conversation->channel
                    ? ($conversation->channel->type?->value === 'evolution'
                        ? ($conversation->channel->config['instance_name'] ?? $conversation->channel->settings['display_name'] ?? $conversation->channel->name)
                        : ($conversation->channel->settings['display_name'] ?? $conversation->channel->name))
                    : null,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'messages' => $conversation->messages->map(fn (Message $m): array => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'type' => $m->type->value,
                    'content' => $m->content,
                    ...$this->attachmentData($m),
                    'read_at' => $m->read_at?->toIso8601String(),
                    'created_at' => $m->created_at?->toIso8601String(),
                    'link_previews' => $m->link_previews,
                    'reactions' => $m->reactions()->get(['id', 'user_jid', 'emoji', 'created_at'])->map(fn ($r) => [
                        'id' => $r->id,
                        'user_jid' => $r->user_jid,
                        'emoji' => $r->emoji,
                        'created_at' => $r->created_at?->toIso8601String(),
                    ])->all(),
                ])->values()->all(),
            ],
        ]);
    }

    private function cleanupOrphanMedia(?Media $mediaRecord, ?int $attachmentMediaId): void
    {
        if (! $mediaRecord) {
            return;
        }

        try {
            if (! empty($mediaRecord->path) && $mediaRecord->disk) {
                Storage::disk($mediaRecord->disk)->delete($mediaRecord->path);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to delete orphan media file', [
                'media_id' => $mediaRecord->id,
                'path' => $mediaRecord->path,
                'error' => $e->getMessage(),
            ]);
        }

        $mediaRecord->delete();
    }

    private function extractProviderError(?string $rawError): ?string
    {
        if (! $rawError) {
            return null;
        }

        $decoded = json_decode($rawError, true);
        if (! is_array($decoded)) {
            return $rawError;
        }

        $message = $decoded['response']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? null;
        if (is_array($message)) {
            $message = implode(' ', $message);
        }

        if (is_string($message) && $message !== '') {
            return $decoded['error'].': '.$message;
        }

        return $rawError;
    }

    private function storeReplyFile(UploadedFile $file, int $userId): int
    {
        $stored = app(MediaStorage::class)->store($file);

        $media = Media::create([
            'disk' => $stored['path'] !== '' ? config('media.disk') : config('media.disk'),
            'path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'name' => $stored['name'],
            'user_id' => $userId,
        ]);

        return $media->id;
    }

    /**
     * Resuelve los datos de attachment de un mensaje.
     * Si el mensaje tiene un `Media` adjunto (admin replies), usa esos datos.
     * Si no, cae al `metadata` (mensajes entrantes del webhook Evolution con media).
     *
     * @return array{attachment_url: ?string, attachment_mime: ?string, attachment_name: ?string, attachment_size: ?int}
     */
    private function attachmentData(Message $m): array
    {
        $meta = $m->metadata ?? [];

        if (! empty($meta['media_base64'])) {
            $mime = $meta['media_mimetype'] ?? 'application/octet-stream';

            return [
                'attachment_url' => 'data:'.$mime.';base64,'.$meta['media_base64'],
                'attachment_mime' => $mime,
                'attachment_name' => $meta['media_filename'] ?? null,
                'attachment_size' => $meta['media_size'] ?? null,
            ];
        }

        $attachment = $m->attachment;
        if ($attachment) {
            return [
                'attachment_url' => $attachment->url(),
                'attachment_mime' => $attachment->mime_type,
                'attachment_name' => $attachment->name,
                'attachment_size' => $attachment->size,
            ];
        }

        if (! empty($meta['media_url'])) {
            return [
                'attachment_url' => $meta['media_url'],
                'attachment_mime' => $meta['media_mimetype'] ?? null,
                'attachment_name' => $meta['media_filename'] ?? null,
                'attachment_size' => $meta['media_size'] ?? null,
            ];
        }

        return [
            'attachment_url' => null,
            'attachment_mime' => null,
            'attachment_name' => null,
            'attachment_size' => null,
        ];
    }

    public function read(Conversation $conversation, Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('view chats'), 403);

        $this->service->markAsRead($conversation, $request->user()->id);

        return back();
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        abort_unless(request()->user()?->can('delete chatbot conversations'), 403);

        $mediaIds = $conversation->messages()
            ->whereNotNull('attachment_media_id')
            ->pluck('attachment_media_id')
            ->unique()
            ->values()
            ->all();

        $conversation->messages()->forceDelete();
        $conversation->forceDelete();

        $this->purgeMedia($mediaIds);

        return to_route('chatbot.admin.chats')->with('success', 'Conversación, mensajes y archivos eliminados permanentemente.');
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($request->user()?->can('view chats'), 403);

        $data = $request->validate([
            'status' => ['nullable', 'in:open,closed'],
        ]);

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $conversation->status = $data['status'];
        }

        $conversation->save();

        return back()->with('success', 'Conversación actualizada.');
    }

    /**
     * @param  list<int>  $mediaIds
     */
    private function purgeMedia(array $mediaIds): void
    {
        if (empty($mediaIds)) {
            return;
        }

        $medias = Media::whereIn('id', $mediaIds)->get();

        foreach ($medias as $media) {
            try {
                if ($media->path && \Storage::disk($media->disk ?? 'public')->exists($media->path)) {
                    \Storage::disk($media->disk ?? 'public')->delete($media->path);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete media file from disk', [
                    'media_id' => $media->id,
                    'path' => $media->path,
                    'error' => $e->getMessage(),
                ]);
            }

            $media->delete();
        }
    }

    private function previewFor(?Message $message): ?string
    {
        if (! $message) {
            return null;
        }

        $content = trim((string) $message->content);
        $isPlaceholder = $content !== '' && (bool) preg_match('/^\[(Imagen|Video|Audio|Archivo|Sticker|Documento)\]$/iu', $content);

        if ($isPlaceholder) {
            $type = $message->type->value;
            $name = $message->attachment?->name;
            $label = match ($type) {
                'image' => '📷 Imagen',
                'video' => '🎬 Video',
                'audio' => '🎵 Audio',
                'sticker' => 'Sticker',
                'file' => '📎 Archivo',
                default => '📎 Adjunto',
            };

            return $name ? "{$label} · {$name}" : $label;
        }

        return $content !== '' ? $content : null;
    }

    /**
     * Detecta mensajes sin `link_previews` pero con URLs en el contenido
     * y dispara un job en bulk para procesarlos. Idempotente: si todos los
     * mensajes ya tienen previews o están vacíos, no hace nada.
     */
    private function dispatchMissingLinkPreviews(Conversation $conversation): void
    {
        $messages = $conversation->messages
            ->filter(fn (Message $m): bool => $m->content !== null
                && $m->content !== ''
                && $m->link_previews === null);

        if ($messages->isEmpty()) {
            return;
        }

        $urlRegex = '#https?://[^\s<>"\'\\)\]]+#i';
        $idsToProcess = [];
        foreach ($messages as $message) {
            if (preg_match($urlRegex, (string) $message->content) === 1) {
                $idsToProcess[] = $message->id;
            } else {
                $message->forceFill(['link_previews' => ['version' => 1, 'items' => []]])->save();
            }
        }

        if ($idsToProcess !== []) {
            FetchLinkPreviewsJob::dispatch($idsToProcess);
        }
    }
}
