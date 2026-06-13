<?php

namespace Modules\ChatBot\Channels\Evolution;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\ChatBot\Models\Conversation;
use Spatie\Permission\Models\Role;

class EvolutionUserLinker
{
    /**
     * Garantiza que la conversación tenga un User asociado.
     *
     * Reglas:
     *  - Si la conversación ya tiene user_id, no hace nada.
     *  - Busca primero por phone (sin el sufijo @s.whatsapp.net) y luego por whatsapp_jid.
     *  - Si no encuentra ninguno, AUTO-CREA el User usando los datos del webhook:
     *      name        = pushName (o "Visitante WhatsApp" como fallback)
     *      phone       = parte numérica del remoteJid
     *      whatsapp_jid = remoteJid completo
     *      email       = derivado del phone (formato local, válido)
     *      password    = aleatorio (el user no se loguea con esto)
     *      role        = "user" (rol por defecto; ver RoleSeeder)
     *  - Si encuentra un user existente, NUNCA sobrescribe su name.
     *  - Si encuentra un user por phone pero le falta whatsapp_jid, lo backfilea.
     */
    public function linkOrCreate(Conversation $conversation, ?string $remoteJid, ?string $pushName, ?string $phonePart): void
    {
        if ($conversation->user_id !== null) {
            return;
        }

        $user = null;

        if ($phonePart && $phonePart !== '') {
            $user = User::where('phone', $phonePart)->first();
        }

        if (! $user && $remoteJid) {
            $user = User::where('whatsapp_jid', $remoteJid)->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $pushName ?? '',
                'email' => $this->buildUserEmail($remoteJid, $phonePart),
                'phone' => $phonePart,
                'whatsapp_jid' => $remoteJid,
                'password' => Hash::make(Str::random(40)),
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
            if ($dirty) {
                $user->save();
            }
        }

        $conversation->forceFill(['user_id' => $user->id])->save();
    }

    /**
     * Genera un email válido (RFC 5321) a partir del JID/phone.
     * Antes usaba "{remoteJid}@whatsapp.user" que producía emails con doble "@"
     * (ej: "59169387181@s.whatsapp.net@whatsapp.user") que son inválidos y
     * rompen integraciones como WooCommerce.
     */
    private function buildUserEmail(?string $remoteJid, ?string $phonePart): ?string
    {
        $local = $phonePart ?: ($remoteJid ? explode('@', $remoteJid)[0] : null);
        if (! $local) {
            return null;
        }
        $local = preg_replace('/[^0-9]/', '', $local) ?? '';

        return $local !== '' ? "wa-{$local}@whatsapp.local" : null;
    }
}
