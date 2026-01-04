<?php

declare(strict_types=1);

namespace Blafast\Foundation\Dto;

/**
 * Data Transfer Object for API methods.
 *
 * Represents a callable method exposed via the API,
 * including parameters, HTTP method, and return type.
 */
readonly class ApiMethod
{
    /**
     * Create a new API method instance.
     *
     * @param  array<string, ApiMethodParameter>  $parameters
     * @param  array<string, mixed>|null  $returns
     */
    public function __construct(
        public string $slug,
        public string $method,
        public string $httpMethod,
        public string $description,
        public array $parameters = [],
        public ?array $returns = null,
        public bool $queued = false,
    ) {}

    /**
     * Create an instance from array configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $slug, array $config): self
    {
        $parameters = [];
        foreach ($config['parameters'] ?? [] as $name => $paramConfig) {
            $parameters[$name] = ApiMethodParameter::fromArray($name, $paramConfig);
        }

        return new self(
            slug: $slug,
            method: $config['method'],
            httpMethod: strtoupper($config['http_method'] ?? 'POST'),
            description: $config['description'] ?? '',
            parameters: $parameters,
            returns: $config['returns'] ?? null,
            queued: $config['queued'] ?? false,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'method' => $this->method,
            'http_method' => $this->httpMethod,
            'description' => $this->description,
            'parameters' => array_map(
                fn ($p) => $p->toArray(),
                array_values($this->parameters)
            ),
            'returns' => $this->returns,
            'queued' => $this->queued,
        ];
    }

    /**
     * Build validation rules for all parameters.
     *
     * @return array<string, array<int, string>>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->parameters as $param) {
            $rules["data.attributes.{$param->name}"] = $param->validationRules();
        }

        return $rules;
    }
}
