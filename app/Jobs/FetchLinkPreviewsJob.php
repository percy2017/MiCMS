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
            ->where('type', 'text')
            ->get()
            ->filter(fn (Message $m): bool => ($m->metadata['media_kind'] ?? null) !== 'link');

        foreach ($messages as $message) {
            $content = (string) $message->content;
            $urls = $service->extractUrls($content);

            if ($urls === []) {
                $meta = $message->metadata ?? [];
                $meta['media_kind'] = 'text';
                $message->forceFill(['metadata' => $meta])->save();

                continue;
            }

            try {
                $items = $service->fetchMany($urls);
                $firstItem = $items[0] ?? null;

                $meta = $message->metadata ?? [];
                $meta['media_kind'] = 'link';
                $meta['media_external_url'] = $firstItem['url'] ?? $firstItem['final_url'] ?? null;
                $meta['media_preview'] = $firstItem;

                $message->forceFill([
                    'metadata' => $meta,
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
