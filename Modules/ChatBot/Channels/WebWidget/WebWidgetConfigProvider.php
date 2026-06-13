<?php

namespace Modules\ChatBot\Channels\WebWidget;

class WebWidgetConfigProvider
{
    /**
     * @return array<int, array{key:string,label:string,type:string,required:bool,help?:string,options?:array}>
     */
    public function settingsFields(): array
    {
        return [
            [
                'key' => 'title',
                'label' => 'Título',
                'type' => 'text',
                'required' => true,
            ],
            [
                'key' => 'subtitle',
                'label' => 'Subtítulo',
                'type' => 'text',
                'required' => false,
            ],
            [
                'key' => 'greeting',
                'label' => 'Mensaje de bienvenida',
                'type' => 'textarea',
                'required' => false,
            ],
            [
                'key' => 'position',
                'label' => 'Posición',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'left', 'label' => 'Izquierda'],
                    ['value' => 'right', 'label' => 'Derecha'],
                ],
            ],
            [
                'key' => 'require_auth',
                'label' => 'Requerir autenticación',
                'type' => 'boolean',
                'required' => false,
            ],
            [
                'key' => 'show_typing',
                'label' => 'Mostrar indicador de escritura',
                'type' => 'boolean',
                'required' => false,
            ],
            [
                'key' => 'offline_message',
                'label' => 'Mensaje fuera de horario',
                'type' => 'textarea',
                'required' => false,
            ],
        ];
    }
}
