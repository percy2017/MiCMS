<?php

namespace Modules\ChatBot\Channels\OpenWa;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\ChatBot\Models\Conversation;
use Spatie\Permission\Models\Role;

class OpenWaUserLinker
{
    /**
     * Garantiza que la conversación tenga un User asociado.
     *
     * Reglas:
     *  - Si la conversación ya tiene user_id, no hace nada.
     *  - Busca primero por phone (parte numérica del chatId) y luego por whatsapp_jid.
     *  - Si no encuentra ninguno, AUTO-CREA el User usando los datos del webhook:
     *      name        = pushName del contacto (o "Visitante OpenWA" como fallback)
     *      phone       = parte numérica del chatId
     *      whatsapp_jid = chatId completo (ej: "59169387181@c.us")
     *      email       = derivado del phone (formato local, válido)
     *      password    = aleatorio (el user no se loguea con esto)
     *      role        = "user" (rol por defecto)
     *  - Si encuentra un user existente, NUNCA sobrescribe su name.
     *  - Si encuentra un user por phone pero le falta whatsapp_jid, lo backfilea.
     */
    public function linkOrCreate(Conversation $conversation, ?string $chatId, ?string $pushName): void
    {
        if ($conversation->user_id !== null) {
            return;
        }

        $phonePart = $chatId ? (explode('@', $chatId)[0] ?? null) : null;

        $user = null;

        if ($phonePart && $phonePart !== '') {
            $user = User::where('phone', $phonePart)->first();
        }

        if (! $user && $chatId) {
            $user = User::where('whatsapp_jid', $chatId)->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $pushName ?? '',
                'email' => $this->buildUserEmail($phonePart),
                'phone' => $phonePart,
                'whatsapp_jid' => $chatId,
                'password' => Hash::make(Str::random(40)),
                'is_whatsapp_business' => false,
            ]);

            $defaultRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
            $user->assignRole($defaultRole);
        } else {
            $dirty = false;
            if ($chatId && empty($user->whatsapp_jid)) {
                $user->whatsapp_jid = $chatId;
                $dirty = true;
            }
            if ($dirty) {
                $user->save();
            }
        }

        $conversation->forceFill(['user_id' => $user->id])->save();
    }

    /**
     * Genera un email válido (RFC 5321) a partir del phone.
     * Formato: "owa-{phone}@openwa.local" para evitar colisiones con Evolution.
     */
    private function buildUserEmail(?string $phonePart): ?string
    {
        if (! $phonePart) {
            return null;
        }
        $local = preg_replace('/[^0-9]/', '', $phonePart) ?? '';

        return $local !== '' ? "owa-{$local}@openwa.local" : null;
    }
}
