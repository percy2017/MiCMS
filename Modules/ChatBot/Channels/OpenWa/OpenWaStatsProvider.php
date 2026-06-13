<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;

/**
 * Para la UI: lista las sesiones disponibles en OpenWA que aún no están vinculadas a un Channel.
 * Para cada Channel existente: devuelve su status actual.
 */
class OpenWaStatsProvider
{
    /**
     * Lista todas las sesiones disponibles en OpenWA, marcando cuáles ya están vinculadas a un Channel.
     *
     * @return array{
     *     configured: bool,
     *     sessions: list<array<string, mixed>>,
     *     error?: string,
     * }
     */
    public function listAvailableSessions(): array
    {
        $client = new OpenWaApiClient;

        if (! $client->isConfigured()) {
            return [
                'configured' => false,
                'sessions' => [],
                'error' => 'OpenWA no está configurado. Define OPENWA_BASE_URL y OPENWA_API_KEY en .env.',
            ];
        }

        $response = $client->listSessions();
        if (! $response->successful()) {
            return [
                'configured' => true,
                'sessions' => [],
                'error' => 'Error al conectar con OpenWA: HTTP '.$response->status(),
            ];
        }

        $sessions = OpenWaApiClient::extractData($response);
        $linkedNames = [];
        foreach (Channel::where('type', ChannelType::OpenWa)
            ->where('enabled', true)
            ->get() as $ch) {
            $name = is_array($ch->config ?? null) ? ($ch->config['session_name'] ?? null) : null;
            if ($name) {
                $linkedNames[$name] = true;
            }
        }

        $list = [];
        foreach ($sessions as $s) {
            $name = $s['name'] ?? null;
            if (! $name) {
                continue;
            }
            $list[] = [
                'name' => $name,
                'openwa_id' => $s['id'] ?? null,
                'status' => $s['status'] ?? 'UNKNOWN',
                'phone' => $s['phone'] ?? null,
                'push_name' => $s['pushName'] ?? null,
                'connected_at' => $s['connectedAt'] ?? null,
                'already_linked' => isset($linkedNames[$name]),
            ];
        }

        return [
            'configured' => true,
            'sessions' => $list,
        ];
    }

    /**
     * Status de un Channel específico (basado en su session_name).
     *
     * @return array<string, mixed>
     */
    public function stats(Channel $channel): array
    {
        $config = $channel->config ?? [];
        $sessionName = $config['session_name'] ?? '';

        if ($sessionName === '') {
            return [
                'connected' => false,
                'error' => 'Canal sin session_name configurado',
                'session' => '',
            ];
        }

        $client = new OpenWaApiClient;
        if (! $client->isConfigured()) {
            return [
                'connected' => false,
                'error' => 'OpenWA no está configurado en .env',
                'session' => $sessionName,
            ];
        }

        try {
            $response = $client->listSessions();
            if (! $response->successful()) {
                return [
                    'connected' => false,
                    'error' => 'Error al conectar: HTTP '.$response->status(),
                    'session' => $sessionName,
                ];
            }

            $sessions = OpenWaApiClient::extractData($response);
            $current = null;
            foreach ($sessions as $s) {
                if (($s['name'] ?? null) === $sessionName) {
                    $current = $s;
                    break;
                }
            }

            if (! $current) {
                return [
                    'connected' => false,
                    'error' => "La sesión '{$sessionName}' ya no existe en OpenWA.",
                    'session' => $sessionName,
                ];
            }

            $status = $current['status'] ?? 'UNKNOWN';
            $connected = $status === 'CONNECTED';

            return [
                'connected' => $connected,
                'state' => $status,
                'session' => $sessionName,
                'openwa_id' => $current['id'] ?? null,
                'phone' => $current['phone'] ?? null,
                'push_name' => $current['pushName'] ?? null,
                'connected_at' => $current['connectedAt'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'session' => $sessionName,
            ];
        }
    }
}
