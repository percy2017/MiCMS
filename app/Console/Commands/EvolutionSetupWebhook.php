<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\Evolution\EvolutionApiClient;
use Modules\ChatBot\Models\Channel;

#[Signature('evolution:setup-webhook {--channel= : ID del canal a reconfigurar (default: todos los evolution)}')]
#[Description('Re-configura el webhook de Evolution API con TODOS los eventos necesarios (MESSAGES_UPSERT, MESSAGES_UPDATE, CONNECTION_UPDATE, etc).')]
class EvolutionSetupWebhook extends Command
{
    public function handle(): int
    {
        $channelId = $this->option('channel');
        $query = Channel::query()->where('type', 'evolution')->where('enabled', true);
        if ($channelId) {
            $query->where('id', (int) $channelId);
        }
        $channels = $query->get();
        if ($channels->isEmpty()) {
            $this->error('No hay canales Evolution activos.');

            return self::FAILURE;
        }

        $events = ['MESSAGES_UPSERT'];

        foreach ($channels as $ch) {
            $cfg = (array) $ch->config;
            $serverUrl = rtrim((string) ($cfg['server_url'] ?? ''), '/');
            $apiKey = (string) ($cfg['api_key'] ?? '');
            $instanceName = (string) ($cfg['instance_name'] ?? '');
            if ($serverUrl === '' || $apiKey === '' || $instanceName === '') {
                $this->warn("Canal {$ch->id} ({$ch->name}): config incompleta, saltando.");

                continue;
            }

            $client = new EvolutionApiClient(serverUrl: $serverUrl, apiKey: $apiKey, instanceName: $instanceName);
            $webhookUrl = url('/api/webhooks/evolution/'.$ch->id);
            $this->line("Canal {$ch->id} ({$instanceName}): configurando webhook a {$webhookUrl}");

            $resp = $client->setWebhook([
                'enabled' => true,
                'url' => $webhookUrl,
                'webhook_by_events' => false,
                'webhook_base64' => true,
                'events' => $events,
            ]);

            $status = $resp->status();
            $body = $resp->body();
            if ($resp->successful()) {
                $this->info("  OK HTTP {$status}: ".substr($body, 0, 200));
            } else {
                $this->error("  FAIL HTTP {$status}: ".substr($body, 0, 200));
            }

            Log::warning('EvolutionSetupWebhook: ejecutado', [
                'channel_id' => $ch->id,
                'instance' => $instanceName,
                'url' => $webhookUrl,
                'status' => $status,
                'body' => substr($body, 0, 500),
            ]);
        }

        return self::SUCCESS;
    }
}
