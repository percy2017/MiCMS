<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\ChatBot\Channels\OpenWa\OpenWaApiClient;
use Modules\ChatBot\Models\Channel;

#[Signature('openwa:setup-webhook
    {channel : ID del Channel en la BD}
    {--url= : URL completa del webhook (default: ruta webhooks.openwa)}
    {--events=* : Eventos a suscribir (default: message.received,message.sent,message.ack,session.status)}
    {--purge : Eliminar webhooks existentes del mismo URL antes de crear}')]
#[Description('Registra (o re-registra) el webhook de OpenWA para un canal dado. Credenciales desde .env.')]
class OpenWaSetupWebhook extends Command
{
    public function handle(): int
    {
        $channelId = (int) $this->argument('channel');
        $channel = Channel::find($channelId);

        if (! $channel) {
            $this->error("No se encontró el canal con ID {$channelId}.");

            return self::FAILURE;
        }

        if ($channel->type->value !== 'openwa') {
            $this->error("El canal {$channelId} no es de tipo OpenWa (es {$channel->type->value}).");

            return self::FAILURE;
        }

        $config = $channel->config ?? [];
        $sessionName = (string) ($config['session_name'] ?? '');

        if ($sessionName === '') {
            $this->error("El canal {$channelId} no tiene 'session_name' en config.");

            return self::FAILURE;
        }

        $client = new OpenWaApiClient;
        if (! $client->isConfigured()) {
            $this->error('OpenWA no está configurado. Define OPENWA_BASE_URL y OPENWA_API_KEY en .env.');

            return self::FAILURE;
        }

        $this->info("Buscando sesión '{$sessionName}'...");
        $sessionsResponse = $client->listSessions();
        if (! $sessionsResponse->successful()) {
            $this->error('No se pudo listar sesiones de OpenWA: HTTP '.$sessionsResponse->status());
            $this->line($sessionsResponse->body());

            return self::FAILURE;
        }

        $sessionId = null;
        foreach (OpenWaApiClient::extractData($sessionsResponse) as $s) {
            if (($s['name'] ?? null) === $sessionName) {
                $sessionId = $s['id'];
                break;
            }
        }

        if (! $sessionId) {
            $this->warn("La sesión '{$sessionName}' no existe. Creándola...");
            $createResponse = $client->createSession($sessionName);
            if (! $createResponse->successful()) {
                $this->error('No se pudo crear la sesión: HTTP '.$createResponse->status());
                $this->line($createResponse->body());

                return self::FAILURE;
            }
            $createdBody = $createResponse->json();
            $sessionId = $createdBody['data']['id'] ?? $createdBody['id'] ?? null;
            if (! $sessionId) {
                $this->error('La respuesta de createSession no contiene un id:');
                $this->line($createResponse->body());

                return self::FAILURE;
            }
            $this->info("Sesión creada: {$sessionId}");
        } else {
            $this->info("Sesión encontrada: {$sessionId}");
        }

        $url = $this->option('url') ?: route('webhooks.openwa', ['channel' => $channel->id]);
        $events = $this->option('events') ?: [
            'message.received',
            'message.sent',
            'message.ack',
            'message.revoked',
            'session.status',
        ];
        $secret = (string) config('chatbot.openwa.webhook_secret', '');

        $payload = [
            'url' => $url,
            'events' => array_values($events),
            'retryCount' => 3,
        ];
        if ($secret !== '') {
            $payload['secret'] = $secret;
        }

        if ($this->option('purge')) {
            $this->info('Eliminando webhooks existentes con la misma URL...');
            $listResponse = $client->listWebhooks($sessionId);
            if ($listResponse->successful()) {
                foreach (OpenWaApiClient::extractData($listResponse) as $existing) {
                    if (($existing['url'] ?? null) === $url) {
                        $delResponse = $client->deleteWebhook($sessionId, $existing['id']);
                        if ($delResponse->successful()) {
                            $this->line("  - Eliminado: {$existing['id']}");
                        }
                    }
                }
            }
        }

        $this->info("Registrando webhook: {$url}");
        $createHookResponse = $client->createWebhook($sessionId, $payload);
        if (! $createHookResponse->successful()) {
            $this->error('No se pudo crear el webhook: HTTP '.$createHookResponse->status());
            $this->line($createHookResponse->body());

            return self::FAILURE;
        }

        $webhook = $createHookResponse->json();
        if (isset($webhook['data']) && is_array($webhook['data'])) {
            $webhook = $webhook['data'];
        }
        $this->info('Webhook registrado correctamente:');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $webhook['id'] ?? '?'],
                ['URL', $webhook['url'] ?? '?'],
                ['Eventos', implode(', ', $webhook['events'] ?? [])],
                ['Active', ($webhook['active'] ?? false) ? 'sí' : 'no'],
                ['Retry', $webhook['retryCount'] ?? 3],
                ['Secret', $secret !== '' ? 'configurado ('.strlen($secret).' chars)' : 'NO (sin firma)'],
            ]
        );

        return self::SUCCESS;
    }
}
