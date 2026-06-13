<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\ChatBot\Models\QuickReply;

class QuickRepliesSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    public const DEFAULTS = [
        [
            'shortcut' => 'saludo',
            'title' => 'Saludo inicial',
            'content' => "¡Hola! 👋 Bienvenido a *Hostbol*.\n\n¿En qué podemos ayudarte hoy?",
            'category' => 'saludos',
            'sort' => 1,
        ],
        [
            'shortcut' => 'gracias',
            'title' => 'Agradecimiento',
            'content' => "Muchas gracias por contactarnos. ✨\n\nTe responderemos a la brevedad.",
            'category' => 'saludos',
            'sort' => 2,
        ],
        [
            'shortcut' => 'horario',
            'title' => 'Horario de atención',
            'content' => "Nuestro horario de atención es:\n\n_Lunes a Viernes_ de *08:00 a 18:00*\n_Sábados_ de *09:00 a 13:00*\n\nFuera de horario, te respondemos el siguiente día hábil.",
            'category' => 'informacion',
            'sort' => 3,
        ],
    ];

    public function run(): void
    {
        $count = 0;
        foreach (self::DEFAULTS as $row) {
            QuickReply::updateOrCreate(
                ['shortcut' => $row['shortcut']],
                $row + ['enabled' => true, 'created_by' => null],
            );
            $count++;
        }

        $this->command?->info("Respuestas rápidas por defecto: {$count} creadas/actualizadas.");
    }
}
