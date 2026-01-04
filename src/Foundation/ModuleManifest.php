<?php

declare(strict_types=1);

namespace Blafast\Foundation\Foundation;

/**
 * Module manifest for caching discovered BlaFast modules.
 *
 * Discovers modules from Composer packages and caches them for performance.
 * Similar to Laravel's package manifest.
 */
class ModuleManifest
{
    /**
     * The manifest file path.
     */
    private string $manifestPath;

    /**
     * The cached manifest data.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $manifest = null;

    /**
     * Create a new module manifest instance.
     */
    public function __construct(string $basePath)
    {
        $this->manifestPath = $basePath.'/bootstrap/cache/blafast-modules.php';
    }

    /**
     * Get all discovered modules.
     *
     * @return array<string, array<string, mixed>>
     */
    public function modules(): array
    {
        if ($this->manifest === null) {
            $this->manifest = is_file($this->manifestPath)
                ? require $this->manifestPath
                : [];
        }

        return $this->manifest;
    }

    /**
     * Get module by name.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        return $this->modules()[$name] ?? null;
    }

    /**
     * Check if module exists.
     */
    public function has(string $name): bool
    {
        return isset($this->modules()[$name]);
    }

    /**
     * Build the manifest from installed packages.
     */
    public function build(): void
    {
        $modules = $this->discoverModules();

        $this->write($modules);
        $this->manifest = $modules;
    }

    /**
     * Discover BlaFast modules from Composer packages.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function discoverModules(): array
    {
        $composerPath = base_path('vendor/composer/installed.json');

        if (! is_file($composerPath)) {
            return [];
        }

        $installed = json_decode(file_get_contents($composerPath), true);
        $packages = $installed['packages'] ?? $installed;

        $modules = [];

        foreach ($packages as $package) {
            if (! $this->isBlaFastModule($package)) {
                continue;
            }

            $modules[$package['name']] = [
                'name' => $package['name'],
                'version' => $package['version'] ?? 'unknown',
                'description' => $package['description'] ?? '',
                'providers' => $package['extra']['laravel']['providers'] ?? [],
                'aliases' => $package['extra']['laravel']['aliases'] ?? [],
                'blafast' => $package['extra']['blafast'] ?? [],
            ];
        }

        return $modules;
    }

    /**
     * Check if package is a BlaFast module.
     *
     * @param  array<string, mixed>  $package
     */
    protected function isBlaFastModule(array $package): bool
    {
        // Check for blafast extra key
        if (isset($package['extra']['blafast'])) {
            return true;
        }

        // Check for blafast type
        if (($package['type'] ?? '') === 'blafast-module') {
            return true;
        }

        // Check for blafast keyword
        if (in_array('blafast-module', $package['keywords'] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Write manifest to file.
     *
     * @param  array<string, array<string, mixed>>  $modules
     */
    protected function write(array $modules): void
    {
        $manifest = '<?php return '.var_export($modules, true).';';

        if (! is_dir(dirname($this->manifestPath))) {
            mkdir(dirname($this->manifestPath), 0755, true);
        }

        file_put_contents($this->manifestPath, $manifest);
    }
}
