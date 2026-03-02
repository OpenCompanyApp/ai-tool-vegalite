<?php

namespace OpenCompany\AiToolVegaLite;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VegaLiteService
{
    /**
     * Render a Vega-Lite JSON specification to raw PNG bytes.
     */
    public function renderToBytes(string $specJson, int $width = 800): string
    {
        $decoded = json_decode($specJson);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $uuid = Str::uuid()->toString();
        $tmpInput = sys_get_temp_dir() . '/' . $uuid . '.json';
        $tmpOutput = sys_get_temp_dir() . '/' . $uuid . '.png';

        file_put_contents($tmpInput, $specJson);

        try {
            $node = $this->findNode();
            $renderScript = $this->findRenderScript();

            $command = [
                $node,
                $renderScript,
                $tmpInput,
                $tmpOutput,
                (string) $width,
            ];

            $process = new Process($command, base_path());
            $process->setTimeout(30);
            $process->setEnv($this->buildEnv($process));
            $process->run();

            if (!$process->isSuccessful()) {
                $error = $process->getErrorOutput() ?: $process->getOutput();
                throw new \RuntimeException('Vega-Lite rendering failed: ' . trim($error));
            }

            if (!file_exists($tmpOutput) || filesize($tmpOutput) === 0) {
                throw new \RuntimeException('Vega-Lite render script produced no output.');
            }

            return file_get_contents($tmpOutput);
        } finally {
            @unlink($tmpInput);
            @unlink($tmpOutput);
        }
    }

    /**
     * Render a Vega-Lite JSON specification to a PNG image on public disk.
     *
     * @return string Public URL path to the generated PNG
     */
    public function render(string $specJson, int $width = 800): string
    {
        $bytes = $this->renderToBytes($specJson, $width);

        Storage::disk('public')->makeDirectory('vegalite');

        $relativePath = 'vegalite/' . Str::uuid()->toString() . '.png';
        Storage::disk('public')->put($relativePath, $bytes);

        return '/storage/' . $relativePath;
    }

    private function buildEnv(Process $process): array
    {
        $env = $process->getEnv();
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        foreach (['/opt/homebrew/bin', '/usr/local/bin', dirname(PHP_BINARY)] as $dir) {
            if (is_dir($dir) && !str_contains($path, $dir)) {
                $path = $dir . ':' . $path;
            }
        }
        $env['PATH'] = $path;

        return $env;
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
        $vendorPath = base_path('vendor/opencompanyapp/ai-tool-vegalite/bin/render.mjs');
        if (file_exists($vendorPath)) {
            return $vendorPath;
        }

        // When using path repository, it may be in tmp/
        $tmpPath = base_path('tmp/ai-tool-vegalite/bin/render.mjs');
        if (file_exists($tmpPath)) {
            return $tmpPath;
        }

        throw new \RuntimeException('Could not find render.cjs script for Vega-Lite rendering.');
    }
}
