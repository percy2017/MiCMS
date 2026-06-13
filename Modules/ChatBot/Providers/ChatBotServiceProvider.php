<?php

namespace Modules\ChatBot\Providers;

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Channels\Evolution\EvolutionChannel;
use Modules\ChatBot\Channels\OpenWa\OpenWaChannel;
use Modules\ChatBot\Channels\WebWidget\WebWidgetChannel;
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
    }

    protected function registerChannels(): void
    {
        $registry = $this->app->make(ChannelRegistry::class);
        $registry->register(new WebWidgetChannel);
        $registry->register(new EvolutionChannel);
        $registry->register(new OpenWaChannel);
    }

    protected function registerWebhookRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(module_path($this->name, '/routes/webhooks.php'));
    }
}
