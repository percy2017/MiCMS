<?php

use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\ChannelInterface;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Services\MessageIngestor;

function makeFailingChannel(ChannelType $type, Throwable $exception): ChannelInterface
{
    return new class($type, $exception) implements ChannelInterface
    {
        public function __construct(
            private readonly ChannelType $type,
            private readonly Throwable $exception,
        ) {}

        public function type(): ChannelType
        {
            return $this->type;
        }

        public function name(): string
        {
            return 'Failing';
        }

        public function description(): string
        {
            return '';
        }

        public function icon(): string
        {
            return 'circle';
        }

        public function accentColor(): string
        {
            return '#000';
        }

        public function configFields(): array
        {
            return [];
        }

        public function settingsFields(): array
        {
            return [];
        }

        public function boot(Channel $channel): void {}

        public function shutdown(Channel $channel): void {}

        public function processIncoming(array $payload, Channel $channel): ?Message
        {
            throw $this->exception;
        }

        public function sendMessage(Conversation $conversation, Message $message): array
        {
            return ['ok' => true];
        }

        public function stats(Channel $channel): array
        {
            return ['connected' => true];
        }
    };
}

test('MessageIngestor captura excepciones y loguea, no rompe el webhook', function (): void {
    Log::spy();

    $exception = new RuntimeException('Boom from processIncoming');
    $failingDriver = makeFailingChannel(ChannelType::Evolution, $exception);

    $registry = app(ChannelRegistry::class);
    $registry->register($failingDriver);

    $channelModel = Channel::factory()->evolution()->create(['enabled' => true]);

    $ingestor = new MessageIngestor($registry);
    $result = $ingestor->ingest($channelModel, [
        'event' => 'messages.upsert',
        'data' => ['key' => ['remoteJid' => '59171146267@s.whatsapp.net']],
    ]);

    expect($result)->toBeNull();
    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $msg, array $ctx) => str_contains($msg, 'MessageIngestor::ingest failed')
            && ($ctx['error'] ?? null) === 'Boom from processIncoming');
});

test('MessageIngestor retorna null si no hay driver registrado', function (): void {
    $registry = app(ChannelRegistry::class);
    $channelModel = Channel::factory()->evolution()->create(['enabled' => true]);

    $ingestor = new MessageIngestor($registry);
    $result = $ingestor->ingest($channelModel, [
        'event' => 'messages.upsert',
    ]);

    expect($result)->toBeNull();
});
