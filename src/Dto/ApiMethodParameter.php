<?php

declare(strict_types=1);

namespace Blafast\Foundation\Dto;

use Blafast\Foundation\Enums\ApiMethodParameterType;

/**
 * Data Transfer Object for API method parameters.
 *
 * Represents a parameter definition for an API method,
 * including type, validation rules, and metadata.
 */
readonly class ApiMethodParameter
{
    /**
     * Create a new API method parameter instance.
     *
     * @param  array<int, string>|null  $enumValues
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $required = false,
        public mixed $default = null,
        public ?string $description = null,
        public ?array $enumValues = null,
    ) {}

    /**
     * Create an instance from array configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $parsed = ApiMethodParameterType::parse($config['type']);

        return new self(
            name: $name,
            type: $config['type'],
            required: $config['required'] ?? false,
            default: $config['default'] ?? null,
            description: $config['description'] ?? null,
            enumValues: $parsed['type'] === ApiMethodParameterType::ENUM
                ? explode(',', $parsed['modifier'] ?? '')
                : null,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'default' => $this->default,
            'description' => $this->description,
            'values' => $this->enumValues,
        ], fn ($v) => $v !== null);
    }

    /**
     * Get Laravel validation rules for this parameter.
     *
     * @return array<int, string>
     */
    public function validationRules(): array
    {
        $rules = [];

        if ($this->required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $parsed = ApiMethodParameterType::parse($this->type);
        $rules[] = $parsed['type']->validationRule();

        // Handle modifiers
        if ($parsed['modifier']) {
            if ($parsed['type'] === ApiMethodParameterType::ARRAY) {
                $rules[] = $parsed['modifier'].'.*';
            } elseif ($parsed['type'] === ApiMethodParameterType::ENUM) {
                $rules[] = 'in:'.$parsed['modifier'];
            }
        }

        return $rules;
    }
}
