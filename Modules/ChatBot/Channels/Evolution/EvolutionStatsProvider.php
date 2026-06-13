<?php

namespace Modules\ChatBot\Channels\Evolution;

use Modules\ChatBot\Models\Channel;

class EvolutionStatsProvider
{
    public function stats(Channel $channel): array
    {
        $config = $channel->config ?? [];

        if (empty($config['server_url']) || empty($config['api_key']) || empty($config['instance_name'])) {
            return [
                'connected' => false,
                'error' => 'Configuración incompleta',
                'instance' => $config['instance_name'] ?? '',
            ];
        }

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($config['server_url'], '/'),
                apiKey: $config['api_key'],
                instanceName: $config['instance_name'],
            );
            $stateResponse = $client->connectionState();

            if ($stateResponse->successful()) {
                $state = $stateResponse->json();

                return [
                    'connected' => ($state['state'] ?? '') === 'open',
                    'state' => $state['state'] ?? 'unknown',
                    'instance' => $config['instance_name'] ?? '',
                    'qr_code' => $state['qrcode'] ?? null,
                ];
            }

            return [
                'connected' => false,
                'error' => 'Error al conectar: HTTP '.$stateResponse->status(),
                'instance' => $config['instance_name'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'instance' => $config['instance_name'] ?? '',
            ];
        }
    }
}
