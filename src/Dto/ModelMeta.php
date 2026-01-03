<?php

declare(strict_types=1);

namespace Blafast\Foundation\Dto;

/**
 * Data Transfer Object for model metadata.
 *
 * Contains all information needed by frontend applications to dynamically
 * render UI components for a model, including fields, endpoints, and methods.
 */
readonly class ModelMeta
{
    /**
     * @param  array<string, string>  $endpoints
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, array<string, mixed>>  $methods
     * @param  array<string, array<string, mixed>>  $mediaCollections
     * @param  array<string, mixed>|null  $search
     * @param  array<string, mixed>|null  $pagination
     */
    public function __construct(
        public string $model,
        public string $label,
        public string $slug,
        public array $endpoints = [],
        public array $fields = [],
        public array $methods = [],
        public array $mediaCollections = [],
        public ?array $search = null,
        public ?array $pagination = null,
    ) {}
}
