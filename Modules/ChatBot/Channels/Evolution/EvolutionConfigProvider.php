<?php

namespace Modules\ChatBot\Channels\Evolution;

class EvolutionConfigProvider
{
    /**
     * @return array<int, array{key:string,label:string,type:string,required:bool,help?:string,placeholder?:string}>
     */
    public function configFields(): array
    {
        return [
            [
                'key' => 'server_url',
                'label' => 'URL del servidor Evolution',
                'type' => 'text',
                'required' => true,
                'help' => 'Ej: https://evolution.tudominio.com',
                'placeholder' => 'https://evolution.percyalvarez.lat',
            ],
            [
                'key' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'help' => 'API key de la instancia Evolution',
            ],
            [
                'key' => 'instance_name',
                'label' => 'Nombre de instancia',
                'type' => 'text',
                'required' => true,
                'help' => 'Usa "Listar instancias" para ver las disponibles',
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
                'help' => 'Nombre que verán los admins',
            ],
            [
                'key' => 'auto_reply',
                'label' => 'Respuesta automática',
                'type' => 'textarea',
                'required' => false,
                'help' => 'Mensaje automático al recibir mensaje nuevo',
            ],
        ];
    }
}
