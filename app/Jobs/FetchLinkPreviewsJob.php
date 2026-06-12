<?php

namespace App\Jobs;

use App\Services\LinkPreviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Events\LinkPreviewsReady;
use Modules\ChatBot\Models\Message;

class FetchLinkPreviewsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 5;

    public int $timeout = 60;

    /**
     * @param  array<int, int>|null  $messageIds  Si es null, procesa el mensaje único.
     *                                            Si es array, procesa varios mensajes en bulk.
     */
    public function __construct(public ?array $messageIds = null, public ?int $messageId = null)
    {
        $this->onQueue('default');
    }

    public function handle(LinkPreviewService $service): void
    {
        $ids = $this->messageIds ?? ($this->messageId ? [$this->messageId] : []);

        if ($ids === []) {
            return;
        }

        $messages = Message::query()
            ->whereIn('id', $ids)
            ->whereNull('link_previews')
            ->get();

        foreach ($messages as $message) {
            $content = (string) $message->content;
            $urls = $service->extractUrls($content);

            if ($urls === []) {
                $message->forceFill(['link_previews' => ['version' => 1, 'items' => []]])->save();
                $this->broadcast($message);

                continue;
            }

            try {
                $items = $service->fetchMany($urls);
                $message->forceFill([
                    'link_previews' => [
                        'version' => 1,
                        'fetched_at' => now()->toIso8601String(),
                        'items' => $items,
                    ],
                ])->save();

                $this->broadcast($message);
            } catch (\Throwable $e) {
                Log::warning('FetchLinkPreviewsJob failed for message', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function broadcast(Message $message): void
    {
        if (! class_exists(LinkPreviewsReady::class)) {
            return;
        }

        try {
            LinkPreviewsReady::dispatch($message);
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast LinkPreviewsReady', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
