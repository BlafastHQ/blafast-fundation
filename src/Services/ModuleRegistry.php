<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Dto\ModuleInfo;
use Blafast\Foundation\Foundation\ModuleManifest;
use Illuminate\Support\Collection;

/**
 * Service for managing BlaFast modules.
 *
 * Provides an interface to discover, register, and boot BlaFast modules
 * that are distributed as Composer packages.
 */
class ModuleRegistry
{
    /**
     * Create a new module registry instance.
     */
    public function __construct(
        private ModuleManifest $manifest
    ) {}

    /**
     * Get all discovered modules.
     *
     * @return Collection<int, ModuleInfo>
     */
    public function all(): Collection
    {
        return collect($this->manifest->modules())
            ->map(fn (array $module) => $this->makeModuleInfo($module))
            ->values();
    }

    /**
     * Get only enabled modules.
     *
     * @return Collection<int, ModuleInfo>
     */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (ModuleInfo $module) => $module->enabled);
    }

    /**
     * Get a module by name.
     */
    public function get(string $name): ?ModuleInfo
    {
        $module = $this->manifest->get($name);

        if ($module === null) {
            return null;
        }

        return $this->makeModuleInfo($module);
    }

    /**
     * Check if a module exists.
     */
    public function has(string $name): bool
    {
        return $this->manifest->has($name);
    }

    /**
     * Get all service providers from enabled modules.
     *
     * @return array<int, string>
     */
    public function getProviders(): array
    {
        return $this->enabled()
            ->flatMap(fn (ModuleInfo $module) => $module->providers)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Rebuild the module manifest.
     */
    public function rebuild(): void
    {
        $this->manifest->build();
    }

    /**
     * Create a ModuleInfo DTO from manifest data.
     *
     * @param  array<string, mixed>  $module
     */
    protected function makeModuleInfo(array $module): ModuleInfo
    {
        return new ModuleInfo(
            name: $module['name'],
            version: $module['version'] ?? 'unknown',
            providers: $module['providers'] ?? [],
            description: $module['description'] ?? '',
            enabled: $module['blafast']['enabled'] ?? true,
        );
    }
}
