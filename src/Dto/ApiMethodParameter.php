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

        // Task 26 (H16): the ARRAY modifier is NOT a rule on this attribute.
        // The old code appended the literal 'email.*' here, which Laravel
        // studly-cased into a nonexistent validateEmail.* method — a
        // BadMethodCallException 500 the moment a value arrived. Element
        // rules live in elementValidationRules() under "attribute.*".
        if ($parsed['modifier'] && $parsed['type'] === ApiMethodParameterType::ENUM) {
            $rules[] = 'in:'.$parsed['modifier'];
        }

        return $rules;
    }

    /**
     * Get the per-element rules for an `array:<type>` parameter, to be
     * registered under the "attribute.*" key — or null when the parameter is
     * not a modified array. The modifier maps through validationRule() (never
     * used raw): `datetime`, `float` and `enum` have no validate* counterpart.
     *
     * @return array<int, string>|null
     */
    public function elementValidationRules(): ?array
    {
        $parsed = ApiMethodParameterType::parse($this->type);

        if ($parsed['type'] !== ApiMethodParameterType::ARRAY || $parsed['modifier'] === null) {
            return null;
        }

        $elementType = ApiMethodParameterType::tryFrom($parsed['modifier']);

        return $elementType === null ? null : [$elementType->validationRule()];
    }

    /**
     * Cast a validated value to what the PHP method signature expects
     * (task 26 / H17). Modified arrays cast each element.
     */
    public function castValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $parsed = ApiMethodParameterType::parse($this->type);

        if ($parsed['type'] === ApiMethodParameterType::ARRAY) {
            $elementType = $parsed['modifier'] !== null
                ? ApiMethodParameterType::tryFrom($parsed['modifier'])
                : null;

            if ($elementType !== null && is_array($value)) {
                return array_map(fn ($v) => $elementType->cast($v), $value);
            }

            return $value;
        }

        return $parsed['type']->cast($value);
    }
}
