<?php

namespace Modules\ChatBot\Channels\OpenWa;

/**
 * Solo expone `session_name` (qué sesión de OpenWA usar).
 * Las credenciales (base_url, api_key, secret) se leen de .env vía config('chatbot.openwa.*').
 */
class OpenWaConfigProvider
{
    /**
     * @return array<int, array{key:string,label:string,type:string,required:bool,help?:string,placeholder?:string}>
     */
    public function configFields(): array
    {
        return [
            [
                'key' => 'session_name',
                'label' => 'Nombre de la sesión OpenWA',
                'type' => 'text',
                'required' => true,
                'help' => 'Nombre exacto de la sesión ya creada en OpenWA (ej: tigo1, entel2). Configurable en .env con OPENWA_BASE_URL y OPENWA_API_KEY.',
                'placeholder' => 'tigo1',
            ],
        ];
    }

    /**
     * @return array<int, array{key:string,label:string,type:string,required:bool,help?:string}>
     */
    public function settingsFields(): array
    {
        return [
            [
                'key' => 'display_name',
                'label' => 'Nombre mostrado',
                'type' => 'text',
                'required' => false,
                'help' => 'Nombre que verán los admins para identificar este inbox OpenWA.',
            ],
            [
                'key' => 'auto_reply',
                'label' => 'Respuesta automática',
                'type' => 'textarea',
                'required' => false,
                'help' => 'Mensaje automático al recibir un mensaje nuevo (opcional).',
            ],
        ];
    }
}
