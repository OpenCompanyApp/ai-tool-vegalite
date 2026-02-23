<?php

namespace OpenCompany\AiToolVegaLite\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolVegaLite\VegaLiteService;

class RenderVegaLite implements Tool
{
    public function __construct(
        private VegaLiteService $vegaLiteService,
    ) {}

    public function description(): string
    {
        return <<<'DESC'
Render a Vega-Lite visualization to a PNG image. Pass a complete Vega-Lite JSON specification and get back a markdown image embed.

IMPORTANT: Always use inline data with "data": {"values": [...]}. Never use "data": {"url": "..."}.

Example spec:
{
  "$schema": "https://vega.github.io/schema/vega-lite/v5.json",
  "data": {"values": [
    {"category": "A", "value": 28},
    {"category": "B", "value": 55},
    {"category": "C", "value": 43}
  ]},
  "mark": "bar",
  "encoding": {
    "x": {"field": "category", "type": "nominal"},
    "y": {"field": "value", "type": "quantitative"}
  }
}

Supported mark types: bar, line, point, area, rect, circle, square, arc, text, tick, rule, trail, boxplot.
Always include "type" in encoding channels: "quantitative", "nominal", "ordinal", or "temporal".
DESC;
    }

    public function handle(Request $request): string
    {
        $spec = trim($request['spec'] ?? '');
        if (empty($spec)) {
            return 'Error: Vega-Lite JSON specification is required. Pass your chart JSON in the "spec" parameter.';
        }

        // Validate it's valid JSON
        json_decode($spec);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'Error: Invalid JSON in spec — ' . json_last_error_msg();
        }

        $title = $request['title'] ?? 'Chart';
        $width = (int) ($request['width'] ?? 800);

        try {
            $url = $this->vegaLiteService->render($spec, $width);

            return "![{$title}]({$url})";
        } catch (\Throwable $e) {
            return 'Error rendering Vega-Lite chart: ' . $e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'spec' => $schema
                ->string()
                ->description('Complete Vega-Lite JSON specification. Must include "data" with inline "values", "mark" type, and "encoding". Always use {"data": {"values": [...]}} for data.')
                ->required(),
            'title' => $schema
                ->string()
                ->description('Chart title used as alt text (default: "Chart").'),
            'width' => $schema
                ->integer()
                ->description('Output width in pixels (default: 800, range: 200–4000).'),
        ];
    }
}
