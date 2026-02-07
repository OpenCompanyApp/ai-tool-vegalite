<?php

namespace OpenCompany\AiToolVegaLite;

use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolVegaLite\Tools\RenderVegaLite;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class VegaLiteToolProvider implements ToolProvider
{
    public function appName(): string
    {
        return 'vegalite';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'charts: bar, line, scatter, heatmap, donut, boxplot',
            'description' => 'Vega-Lite chart rendering',
            'icon' => 'ph:chart-bar',
            'logo' => 'ph:chart-bar',
        ];
    }

    public function tools(): array
    {
        return [
            'render_vegalite' => [
                'class' => RenderVegaLite::class,
                'type' => 'write',
                'name' => 'Render Vega-Lite',
                'description' => 'Render a Vega-Lite JSON specification (bar, line, scatter, area, heatmap, etc.) to a PNG image.',
                'icon' => 'ph:chart-bar',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    public function createTool(string $class, array $context = []): Tool
    {
        return new $class(app(VegaLiteService::class));
    }
}
