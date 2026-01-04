<?php

declare(strict_types=1);

namespace Blafast\Foundation\Api;

/**
 * Fluent builder for API method definitions.
 *
 * Provides a convenient way to define API methods with
 * parameters, return types, and other metadata.
 */
class ApiMethodBuilder
{
    private string $slug;

    private string $method;

    private string $httpMethod = 'POST';

    private string $description = '';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $parameters = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $returns = null;

    private bool $queued = false;

    /**
     * Create a new API method builder.
     */
    public static function make(string $slug, string $method): self
    {
        $builder = new self;
        $builder->slug = $slug;
        $builder->method = $method;

        return $builder;
    }

    /**
     * Set HTTP method to POST.
     */
    public function post(): self
    {
        $this->httpMethod = 'POST';

        return $this;
    }

    /**
     * Set HTTP method to GET.
     */
    public function get(): self
    {
        $this->httpMethod = 'GET';

        return $this;
    }

    /**
     * Set the description translation key.
     */
    public function description(string $key): self
    {
        $this->description = $key;

        return $this;
    }

    /**
     * Add a parameter to the method.
     */
    public function parameter(
        string $name,
        string $type,
        bool $required = false,
        mixed $default = null,
        ?string $description = null
    ): self {
        $this->parameters[$name] = [
            'type' => $type,
            'required' => $required,
            'default' => $default,
            'description' => $description,
        ];

        return $this;
    }

    /**
     * Add a required parameter.
     */
    public function requiredParam(string $name, string $type, ?string $description = null): self
    {
        return $this->parameter($name, $type, true, null, $description);
    }

    /**
     * Add an optional parameter.
     */
    public function optionalParam(string $name, string $type, mixed $default = null, ?string $description = null): self
    {
        return $this->parameter($name, $type, false, $default, $description);
    }

    /**
     * Set the return type.
     *
     * @param  array<string, mixed>|null  $schema
     */
    public function returns(string $type, ?array $schema = null): self
    {
        $this->returns = ['type' => $type];
        if ($schema) {
            $this->returns['schema'] = $schema;
        }

        return $this;
    }

    /**
     * Set return type as file.
     */
    public function returnsFile(string $mime): self
    {
        $this->returns = ['type' => 'file', 'mime' => $mime];

        return $this;
    }

    /**
     * Mark method as queued (deferred execution).
     */
    public function queued(bool $queued = true): self
    {
        $this->queued = $queued;

        return $this;
    }

    /**
     * Build the method array.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'method' => $this->method,
            'http_method' => $this->httpMethod,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'returns' => $this->returns,
            'queued' => $this->queued,
        ];
    }
}
