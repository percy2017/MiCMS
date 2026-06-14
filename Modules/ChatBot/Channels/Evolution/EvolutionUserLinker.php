<?php

namespace Modules\ChatBot\Channels\Evolution;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Spatie\Permission\Models\Role;

class EvolutionUserLinker
{
    /**
     * Garantiza que la conversación tenga un User asociado.
     *
     * Reglas:
     *  - Si la conversación ya tiene user_id, NO hace nada (assume el user
     *    ya está bien con su name + whatsapp_jid).
     *  - Si no tiene user_id:
     *      1. Busca primero por phone (sin el sufijo @s.whatsapp.net).
     *      2. Si no encuentra, busca por whatsapp_jid.
     *      3. Si no encuentra ninguno, AUTO-CREA el User usando los datos
     *         del webhook + la API de Evolution (fetchProfile) para obtener
     *         el nombre real del visitante.
     *  - Si el user creado/encontrado tiene name vacío y después se conoce
     *    el nombre (ej. pushName de un upsert posterior), se actualiza.
     */
    public function linkOrCreate(Conversation $conversation, Channel $channel, ?string $remoteJid, ?string $pushName, ?string $phonePart): void
    {
        $user = null;

        if ($phonePart && $phonePart !== '') {
            $user = User::where('phone', $phonePart)->first();
        }

        if (! $user && $remoteJid) {
            $user = User::where('whatsapp_jid', $remoteJid)->first();
        }

        $resolvedName = $this->resolveVisitorName($channel, $phonePart, $pushName);

        if (! $user) {
            $user = User::create([
                'name' => $resolvedName ?? '',
                'email' => $this->buildUserEmail($phonePart),
                'phone' => $phonePart,
                'whatsapp_jid' => $remoteJid,
                'password' => bcrypt(\Illuminate\Support\Str::random(40)),
                'is_whatsapp_business' => false,
            ]);

            $defaultRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
            $user->assignRole($defaultRole);
        } else {
            $dirty = false;
            if ($remoteJid && empty($user->whatsapp_jid)) {
                $user->whatsapp_jid = $remoteJid;
                $dirty = true;
            }
            if (empty($user->name) && $resolvedName !== null && $resolvedName !== '') {
                $user->name = $resolvedName;
                $dirty = true;
            }
            if ($dirty) {
                $user->save();
            }
        }

        if ($conversation->user_id !== $user->id) {
            $conversation->forceFill(['user_id' => $user->id])->save();
        }
    }

    /**
     * Resuelve el nombre del visitante consultando la API de Evolution primero
     * (que devuelve el nombre real del contacto), y usando el pushName del
     * webhook como fallback.
     */
    private function resolveVisitorName(Channel $channel, ?string $phonePart, ?string $pushName): ?string
    {
        $fromApi = $this->fetchProfileName($channel, $phonePart);
        if ($fromApi !== null) {
            return $fromApi;
        }

        if ($pushName !== null && trim($pushName) !== '') {
            return trim($pushName);
        }

        return null;
    }

    /**
     * Llama a Evolution API /chat/fetchProfile/{instance} para obtener el
     * nombre real del contacto (no el pushName del webhook, que a veces
     * viene vacío o desactualizado).
     */
    private function fetchProfileName(Channel $channel, ?string $phonePart): ?string
    {
        if (! $phonePart) {
            return null;
        }

        $serverUrl = rtrim((string) ($channel->config['server_url'] ?? ''), '/');
        $apiKey = (string) ($channel->config['api_key'] ?? '');
        $instanceName = (string) ($channel->config['instance_name'] ?? '');

        if ($serverUrl === '' || $apiKey === '' || $instanceName === '') {
            return null;
        }

        try {
            $client = new EvolutionApiClient(
                serverUrl: $serverUrl,
                apiKey: $apiKey,
                instanceName: $instanceName,
            );
            $response = $client->fetchProfile($phonePart);

            if (! $response->successful()) {
                return null;
            }

            $profile = $response->json();
            $name = $profile['name'] ?? $profile['pushName'] ?? $profile['verifiedName'] ?? null;

            return is_string($name) && trim($name) !== '' ? trim($name) : null;
        } catch (\Throwable $e) {
            Log::warning('EvolutionUserLinker: fetchProfile failed', [
                'channel_id' => $channel->id,
                'phone' => $phonePart,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Genera un email válido (RFC 5321) a partir del phone.
     */
    private function buildUserEmail(?string $phonePart): ?string
    {
        if (! $phonePart) {
            return null;
        }
        $local = preg_replace('/[^0-9]/', '', $phonePart) ?? '';

        return $local !== '' ? "wa-{$local}@whatsapp.local" : null;
    }
}
