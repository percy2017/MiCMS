<?php

namespace Modules\ChatBot\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Spatie\Permission\Models\Role;

class ChatBotAuthService
{
    /**
     * Login or register a visitor and return the active conversation.
     *
     * @return array{user: User, conversation: Conversation, is_new: bool}
     */
    public function startSession(string $email, ?string $password, ?string $name, string $action, ?string $pageUrl = null): array
    {
        if ($action === 'resume') {
            $user = Auth::user();
            if (! $user) {
                throw ValidationException::withMessages(['auth' => 'No hay sesión activa.']);
            }

            return $this->resumeSession($user, $pageUrl);
        }

        if ($action === 'login') {
            return $this->login($email, $password, $pageUrl);
        }

        return $this->register($email, $password, $name, $pageUrl);
    }

    /**
     * @return array{user: User, conversation: Conversation, is_new: bool}
     */
    protected function login(string $email, ?string $password, ?string $pageUrl): array
    {
        if (! $password) {
            throw ValidationException::withMessages(['password' => 'La contraseña es obligatoria.']);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages(['email' => 'Credenciales inválidas.']);
        }

        $user = Auth::user();

        return [
            'user' => $user,
            'conversation' => $this->getOrCreateConversation($user, $pageUrl),
            'is_new' => false,
        ];
    }

    /**
     * @return array{user: User, conversation: Conversation, is_new: bool}
     */
    protected function register(string $email, ?string $password, ?string $name, ?string $pageUrl): array
    {
        if (! $password || strlen($password) < 8) {
            throw ValidationException::withMessages(['password' => 'La contraseña debe tener al menos 8 caracteres.']);
        }

        if (! $name) {
            throw ValidationException::withMessages(['name' => 'El nombre es obligatorio.']);
        }

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este email ya está registrado. Inicia sesión.']);
        }

        $user = DB::transaction(function () use ($email, $name, $password) {
            $newUser = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            if (! $newUser->hasRole('user')) {
                $role = Role::findByName('user', 'web');
                if ($role) {
                    $newUser->assignRole($role);
                }
            }

            return $newUser;
        });

        Auth::login($user);

        return [
            'user' => $user,
            'conversation' => $this->getOrCreateConversation($user, $pageUrl, isNew: true),
            'is_new' => true,
        ];
    }

    /**
     * @return array{user: User, conversation: Conversation, is_new: bool}
     */
    public function resumeSession(User $user, ?string $pageUrl = null): array
    {
        return [
            'user' => $user,
            'conversation' => $this->getOrCreateConversation($user, $pageUrl),
            'is_new' => false,
        ];
    }

    protected function getOrCreateConversation(User $user, ?string $pageUrl, bool $isNew = false): Conversation
    {
        $channel = Channel::where('type', 'web_widget')->first();
        if (! $channel) {
            $channel = Channel::create([
                'type' => 'web_widget',
                'name' => 'Widget Web',
                'enabled' => true,
                'settings' => [],
            ]);
        }

        $existing = Conversation::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('last_message_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Conversation::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'page_url' => $pageUrl,
            'status' => 'open',
            'last_message_at' => now(),
            'unread_by_admin' => 0,
        ]);
    }
}
