<?php

namespace Modules\ChatBot\Channels\WebWidget;

use Modules\ChatBot\Models\Channel;

class WebWidgetStatsProvider
{
    /**
     * @return array<string, mixed>
     */
    public function stats(Channel $channel): array
    {
        $settings = $channel->settings ?? [];

        return [
            'configured' => ! empty($settings['title']),
            'title' => $settings['title'] ?? 'No configurado',
        ];
    }
}
