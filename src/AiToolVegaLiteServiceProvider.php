<?php

namespace OpenCompany\AiToolVegaLite;

use Illuminate\Support\ServiceProvider;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class AiToolVegaLiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VegaLiteService::class);
    }

    public function boot(): void
    {
        if ($this->app->bound(ToolProviderRegistry::class)) {
            $this->app->make(ToolProviderRegistry::class)
                ->register(new VegaLiteToolProvider());
        }
    }
}
