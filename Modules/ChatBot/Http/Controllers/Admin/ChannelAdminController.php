<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;

class ChannelAdminController extends Controller
{
    public function __construct(
        private readonly ChannelRegistry $registry,
    ) {}

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $channels = Channel::orderBy('sort')->get()->map(function (Channel $c): array {
            $base = [
                'id' => $c->id,
                'type' => $c->type->value,
                'name' => $c->settings['display_name'] ?? $c->name ?? $c->type->value,
                'enabled' => (bool) $c->enabled,
                'url' => match ($c->type->value) {
                    'evolution' => route('chatbot.admin.evolution.create'),
                    'openwa' => route('chatbot.admin.openwa.create'),
                    'web_widget' => route('chatbot.admin.widget.edit', $c),
                    default => '#',
                },
            ];

            if ($c->type->value === 'evolution') {
                $config = $this->safeConfig($c);
                $base['instance_name'] = $config['instance_name'] ?? null;
                $base['instance_id'] = $config['instance_id'] ?? null;
                $base['profile_name'] = $config['profile_name'] ?? null;
                $base['profile_picture_url'] = $config['profile_picture_url'] ?? null;
                $base['owner_jid'] = $config['owner_jid'] ?? null;

                $base['connection_status'] = 'unknown';
                try {
                    $stats = $this->registry->get('evolution')?->stats($c) ?? [];
                    $base['connection_status'] = $stats['state'] ?? 'unknown';
                } catch (\Throwable) {
                    // keep 'unknown'
                }
            }

            if ($c->type->value === 'openwa') {
                $config = $this->safeConfig($c);
                $base['session_name'] = $config['session_name'] ?? null;
            }

            if ($c->type->value === 'web_widget') {
                $base['widget_title'] = $c->settings['title'] ?? null;
                $base['public_key'] = $c->public_key;
                $base['allowed_domain'] = $c->allowed_domain;
                $base['conversations_count'] = $c->conversations()->count();
            }

            return $base;
        });

        return Inertia::render('ChatBot::Canales/Index', [
            'channels' => $channels,
        ]);
    }

    public function show(Channel $channel): RedirectResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        return match ($channel->type->value) {
            'web_widget' => redirect()->route('chatbot.admin.widget.edit', $channel),
            default => redirect()->route('chatbot.admin.canales'),
        };
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        abort_unless(request()->user()?->can('view chatbot'), 403);

        $channelId = $channel->id;
        $channelType = $channel->type;
        $channelTypeValue = $channelType?->value ?? (string) ($channel->getAttributes()['type'] ?? 'unknown');

        // Pre-collect media IDs referenced by this channel's messages.
        // We collect them BEFORE deletion to know what to purge from disk + DB.
        $mediaIds = $channel->conversations()
            ->with('messages')
            ->get()
            ->flatMap(fn ($conv) => $conv->messages->pluck('attachment_media_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $messageIds = $channel->conversations()
            ->with('messages')
            ->get()
            ->flatMap(fn ($conv) => $conv->messages->pluck('id'));

        $conversationsCount = $channel->conversations()->count();
        $messagesCount = $messageIds->count();
        $reactionsCount = $messageIds->isNotEmpty()
            ? \Modules\ChatBot\Models\MessageReaction::query()->whereIn('message_id', $messageIds)->count()
            : 0;

        $evolutionConfig = $channelType === ChannelType::Evolution ? $this->safeConfig($channel) : [];

        \DB::transaction(function () use ($channel) {
            // forceDelete removes the soft-deleted rows; FK cascade cleans
            // message_reactions when messages are removed.
            $channel->conversations()->each(fn ($conv) => $conv->messages()->forceDelete());
            $channel->conversations()->forceDelete();
            $channel->forceDelete();
        });

        // Purge media files (disk + DB rows) — done after the transaction
        // because the files are external to the DB.
        $purgedMedia = $this->purgeMedia($mediaIds);

        // Best-effort: disconnect from Evolution (if applicable)
        if ($channelType === ChannelType::Evolution) {
            try {
                $client = new \Modules\ChatBot\Channels\Evolution\EvolutionApiClient(
                    serverUrl: rtrim((string) ($evolutionConfig['server_url'] ?? ''), '/'),
                    apiKey: (string) ($evolutionConfig['api_key'] ?? ''),
                    instanceName: (string) ($evolutionConfig['instance_name'] ?? ''),
                );
                $client->disconnectInstance();
            } catch (\Throwable $e) {
                Log::warning('Evolution disconnectInstance failed on channel delete', [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Channel destroyed with cascade', [
            'channel_id' => $channelId,
            'channel_type' => $channelTypeValue,
            'conversations_deleted' => $conversationsCount,
            'messages_deleted' => $messagesCount,
            'reactions_deleted' => $reactionsCount,
            'media_purged' => $purgedMedia,
        ]);

        return redirect()
            ->route('chatbot.admin.canales')
            ->with('success', sprintf(
                'Canal eliminado. %d conversaciones, %d mensajes, %d reacciones y %d medios purgados.',
                $conversationsCount,
                $messagesCount,
                $reactionsCount,
                $purgedMedia,
            ));
    }

    /**
     * @return array<string, mixed>
     */
    private function safeConfig(Channel $c): array
    {
        $cfg = $c->config;
        if (is_string($cfg)) {
            $cfg = json_decode($cfg, true);
        }

        return is_array($cfg) ? $cfg : [];
    }

    private function purgeMedia(array $mediaIds): int
    {
        if (empty($mediaIds)) {
            return 0;
        }

        $medias = Media::whereIn('id', $mediaIds)->get();
        $purged = 0;

        foreach ($medias as $media) {
            try {
                if ($media->path && Storage::disk($media->disk ?? 'public')->exists($media->path)) {
                    Storage::disk($media->disk ?? 'public')->delete($media->path);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete media file from disk', [
                    'media_id' => $media->id,
                    'path' => $media->path,
                    'error' => $e->getMessage(),
                ]);
            }

            $media->delete();
            $purged++;
        }

        return $purged;
    }
}
