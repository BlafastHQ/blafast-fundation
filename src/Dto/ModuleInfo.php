<?php

declare(strict_types=1);

namespace Blafast\Foundation\Dto;

/**
 * Data Transfer Object for module information.
 *
 * Represents a BlaFast module discovered from Composer packages.
 */
readonly class ModuleInfo
{
    /**
     * Create a new module info instance.
     *
     * @param  string  $name  The module package name (e.g., 'blafast/auth')
     * @param  string  $version  The module version
     * @param  array<int, string>  $providers  Service provider class names
     * @param  string  $description  Module description
     * @param  bool  $enabled  Whether the module is enabled
     */
    public function __construct(
        public string $name,
        public string $version,
        public array $providers,
        public string $description = '',
        public bool $enabled = true,
    ) {}

    /**
     * Convert the module info to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'providers' => $this->providers,
            'description' => $this->description,
            'enabled' => $this->enabled,
        ];
    }
}
