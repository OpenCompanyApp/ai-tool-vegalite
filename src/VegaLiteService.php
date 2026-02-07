<?php

namespace OpenCompany\AiToolVegaLite;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VegaLiteService
{
    /**
     * Render a Vega-Lite JSON specification to a PNG image.
     *
     * @return string Public URL path to the generated PNG
     */
    public function render(string $specJson, int $width = 800): string
    {
        // Validate JSON
        $decoded = json_decode($specJson);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        Storage::disk('public')->makeDirectory('vegalite');

        $uuid = Str::uuid()->toString();
        $relativePath = 'vegalite/' . $uuid . '.png';
        $outputPath = Storage::disk('public')->path($relativePath);

        // Write spec to temp JSON file
        $tmpInput = sys_get_temp_dir() . '/' . $uuid . '.json';
        file_put_contents($tmpInput, $specJson);

        try {
            $node = $this->findNode();
            $renderScript = $this->findRenderScript();

            $command = [
                $node,
                $renderScript,
                $tmpInput,
                $outputPath,
                (string) $width,
            ];

            $process = new Process($command, base_path());
            $process->setTimeout(30);

            // Ensure node is in PATH for queue workers with minimal environments.
            $env = $process->getEnv();
            $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
            foreach (['/opt/homebrew/bin', '/usr/local/bin', dirname(PHP_BINARY)] as $dir) {
                if (is_dir($dir) && !str_contains($path, $dir)) {
                    $path = $dir . ':' . $path;
                }
            }
            $env['PATH'] = $path;
            $process->setEnv($env);

            $process->run();

            if (!$process->isSuccessful()) {
                $error = $process->getErrorOutput() ?: $process->getOutput();

                throw new \RuntimeException('Vega-Lite rendering failed: ' . trim($error));
            }

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException('Vega-Lite render script produced no output.');
            }
        } finally {
            @unlink($tmpInput);
        }

        return '/storage/' . $relativePath;
    }

    /**
     * Find the Node.js binary.
     */
    private function findNode(): string
    {
        $candidates = [
            '/opt/homebrew/bin/node',
            '/usr/local/bin/node',
            '/usr/bin/node',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return 'node';
    }

    /**
     * Find the render.cjs script bundled with this package.
     */
    private function findRenderScript(): string
    {
        // When installed via Composer, the package is in vendor/
        $vendorPath = base_path('vendor/opencompanyapp/ai-tool-vegalite/bin/render.cjs');
        if (file_exists($vendorPath)) {
            return $vendorPath;
        }

        // When using path repository, it may be in tmp/
        $tmpPath = base_path('tmp/ai-tool-vegalite/bin/render.cjs');
        if (file_exists($tmpPath)) {
            return $tmpPath;
        }

        throw new \RuntimeException('Could not find render.cjs script for Vega-Lite rendering.');
    }
}
