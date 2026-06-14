<?php

namespace Modules\ChatBot\Providers;

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Channels\Evolution\EvolutionChannel;
use Modules\ChatBot\Channels\OpenWa\OpenWaChannel;
use Modules\ChatBot\Channels\WebWidget\WebWidgetChannel;
use Modules\ChatBot\Inbox\Evolution\EvolutionInboxStrategy;
use Modules\ChatBot\Inbox\OpenWa\OpenWaInboxStrategy;
use Modules\ChatBot\Inbox\Shared\ChannelInboxService;
use Modules\ChatBot\Inbox\Shared\InboxStrategyRegistry;
use Modules\ChatBot\Services\ChannelManager;
use Modules\ChatBot\Services\MessageIngestor;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ChatBotServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'ChatBot';

    protected string $nameLower = 'chatbot';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->registerChannels();
        $this->registerInboxStrategies();
        $this->registerWebhookRoutes();
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ChannelRegistry::class, function () {
            return new ChannelRegistry;
        });

        $this->app->singleton(ChannelManager::class, function ($app) {
            return new ChannelManager($app->make(ChannelRegistry::class));
        });

        $this->app->singleton(MessageIngestor::class, function ($app) {
            return new MessageIngestor($app->make(ChannelRegistry::class));
        });

        $this->app->singleton(ChannelInboxService::class);

        $this->app->singleton(InboxStrategyRegistry::class, function ($app) {
            return new InboxStrategyRegistry(
                $app->make(EvolutionInboxStrategy::class),
                $app->make(OpenWaInboxStrategy::class),
            );
        });
    }

    protected function registerChannels(): void
    {
        $registry = $this->app->make(ChannelRegistry::class);
        $registry->register(new WebWidgetChannel);
        $registry->register(new EvolutionChannel);
        $registry->register(new OpenWaChannel);
    }

    protected function registerInboxStrategies(): void
    {
        // Forces container to resolve them (so DI works on controllers)
        $this->app->make(InboxStrategyRegistry::class);
    }

    protected function registerWebhookRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(module_path($this->name, '/routes/webhooks.php'));
    }
}

