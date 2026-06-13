<?php

namespace App\Console\Commands;

use App\Jobs\FetchLinkPreviewsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Models\Message;

#[Signature('link-previews:refetch-failed {--conversation= : Only refetch for this conversation} {--message= : Only refetch this specific message} {--limit=200 : Max messages to process} {--purge-cache : Also purge Laravel cache for failed previews so the job re-fetches immediately}')]
#[Description('Re-busca link previews de mensajes cuyo metadata.media_preview contiene errores (error no nulo o sin título/descripción/imagen).')]
class RefetchLinkPreviews extends Command
{
    public function handle(): int
    {
        $messageId = $this->option('message');
        $conversationId = $this->option('conversation');
        $limit = (int) $this->option('limit');
        $purgeCache = (bool) $this->option('purge-cache');

        $query = Message::query()
            ->where('type', 'text')
            ->orderByDesc('id');

        if ($messageId) {
            $query->where('id', (int) $messageId);
        }
        if ($conversationId) {
            $query->where('conversation_id', (int) $conversationId);
        }

        $messages = $query->limit($limit * 5)->get()->filter(function (Message $m): bool {
            $meta = $m->metadata ?? [];
            if (($meta['media_kind'] ?? null) !== 'link') {
                return false;
            }
            $preview = $meta['media_preview'] ?? null;
            if ($preview === null) {
                return true;
            }
            if (is_array($preview) && ! empty($preview['error'])) {
                return true;
            }
            if (is_array($preview) && empty($preview['title']) && empty($preview['description']) && empty($preview['image'])) {
                return true;
            }

            return false;
        })->take($limit);

        if ($messages->isEmpty()) {
            $this->info('No se encontraron mensajes con link previews fallidos.');

            return self::SUCCESS;
        }

        $this->info("Encontrados {$messages->count()} mensaje(s) con previews fallidos.");

        $purged = 0;
        $dispatched = 0;

        foreach ($messages as $message) {
            $preview = $message->metadata['media_preview'] ?? null;
            $url = is_array($preview) ? ($preview['url'] ?? null) : null;

            if ($purgeCache && is_string($url) && $url !== '') {
                Cache::forget('link_preview:'.md5($url));
                $purged++;
            }

            $meta = $message->metadata ?? [];
            unset($meta['media_preview']);
            $message->forceFill(['metadata' => $meta])->save();
            FetchLinkPreviewsJob::dispatch([$message->id]);
            $dispatched++;
        }

        $this->info("Re-despachados: {$dispatched}");
        if ($purgeCache > 0) {
            $this->info("Entradas de cache purgadas: {$purged}");
        }

        Log::info('link-previews:refetch-failed completed', [
            'dispatched' => $dispatched,
            'purged' => $purged,
            'message_ids' => $messages->pluck('id')->toArray(),
        ]);

        return self::SUCCESS;
    }
}
