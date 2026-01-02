<?php

declare(strict_types=1);

namespace Blafast\Foundation\Api;

use Blafast\Foundation\Dto\ApiField;
use Blafast\Foundation\Enums\ApiFieldType;
use Illuminate\Support\Str;

/**
 * Fluent builder for creating API structure definitions.
 */
class ApiStructureBuilder
{
    private string $label = '';

    private string $slug = '';

    /** @var array<ApiField> */
    private array $fields = [];

    /** @var array<string> */
    private array $filters = [];

    /** @var array<string> */
    private array $sorts = [];

    /** @var array<string> */
    private array $includes = [];

    /** @var array{strategy: string, fields: array<string>} */
    private array $search = ['strategy' => 'like', 'fields' => []];

    /** @var array<string, array<string, mixed>> */
    private array $mediaCollections = [];

    /** @var array{default_size?: int, max_size?: int} */
    private array $pagination = [];

    /**
     * Create a new builder instance.
     *
     * @param  class-string  $modelClass
     */
    public static function make(string $modelClass): self
    {
        $builder = new self;
        $builder->slug = Str::kebab(class_basename($modelClass));

        return $builder;
    }

    /**
     * Set the label translation key.
     */
    public function label(string $translationKey): self
    {
        $this->label = $translationKey;

        return $this;
    }

    /**
     * Set the model slug.
     */
    public function slug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Add a field to the structure.
     */
    public function field(
        string $name,
        ApiFieldType $type,
        ?string $label = null,
        bool $sortable = false,
        bool $filterable = false,
        bool $searchable = false,
        ?string $cast = null
    ): self {
        $this->fields[] = new ApiField(
            name: $name,
            label: $label ?? "fields.{$name}",
            type: $type,
            sortable: $sortable,
            filterable: $filterable,
            searchable: $searchable,
            cast: $cast
        );

        return $this;
    }

    /**
     * Add a UUID field.
     */
    public function uuid(string $name, ?string $label = null, bool $sortable = true): self
    {
        return $this->field(
            $name,
            ApiFieldType::UUID,
            $label,
            $sortable,
            filterable: true
        );
    }

    /**
     * Add a string field.
     */
    public function string(
        string $name,
        ?string $label = null,
        bool $searchable = true,
        bool $sortable = true,
        bool $filterable = true
    ): self {
        return $this->field(
            $name,
            ApiFieldType::STRING,
            $label,
            $sortable,
            $filterable,
            $searchable
        );
    }

    /**
     * Add a text field (long text).
     */
    public function text(string $name, ?string $label = null, bool $searchable = true): self
    {
        return $this->field(
            $name,
            ApiFieldType::TEXT,
            $label,
            sortable: false,
            filterable: false,
            searchable: $searchable
        );
    }

    /**
     * Add an integer field.
     */
    public function integer(string $name, ?string $label = null, bool $sortable = true): self
    {
        return $this->field(
            $name,
            ApiFieldType::INTEGER,
            $label,
            $sortable,
            filterable: true
        );
    }

    /**
     * Add a float field.
     */
    public function float(string $name, ?string $label = null, bool $sortable = true): self
    {
        return $this->field(
            $name,
            ApiFieldType::FLOAT,
            $label,
            $sortable,
            filterable: true
        );
    }

    /**
     * Add a decimal field.
     */
    public function decimal(string $name, int $places = 2, ?string $label = null, bool $sortable = true): self
    {
        $this->fields[] = new ApiField(
            name: $name,
            label: $label ?? "fields.{$name}",
            type: ApiFieldType::DECIMAL,
            sortable: $sortable,
            filterable: true,
            decimalPlaces: $places
        );

        return $this;
    }

    /**
     * Add a boolean field.
     */
    public function boolean(string $name, ?string $label = null): self
    {
        return $this->field(
            $name,
            ApiFieldType::BOOLEAN,
            $label,
            sortable: true,
            filterable: true
        );
    }

    /**
     * Add a date field.
     */
    public function date(string $name, ?string $label = null, bool $sortable = true): self
    {
        return $this->field(
            $name,
            ApiFieldType::DATE,
            $label,
            $sortable,
            filterable: true
        );
    }

    /**
     * Add a datetime field.
     */
    public function datetime(string $name, ?string $label = null, bool $sortable = true): self
    {
        return $this->field(
            $name,
            ApiFieldType::DATETIME,
            $label,
            $sortable,
            filterable: true
        );
    }

    /**
     * Add a JSON field.
     */
    public function json(string $name, ?string $label = null): self
    {
        return $this->field(
            $name,
            ApiFieldType::JSON,
            $label,
            sortable: false,
            filterable: false
        );
    }

    /**
     * Add an enum field.
     *
     * @param  array<string>  $values
     */
    public function enum(string $name, array $values, ?string $label = null): self
    {
        $this->fields[] = new ApiField(
            name: $name,
            label: $label ?? "fields.{$name}",
            type: ApiFieldType::ENUM,
            sortable: true,
            filterable: true,
            enumValues: $values
        );

        return $this;
    }

    /**
     * Add a relation field.
     */
    public function relation(string $name, string $relationField, ?string $label = null, bool $sortable = false): self
    {
        $this->fields[] = new ApiField(
            name: $name,
            label: $label ?? "fields.{$name}",
            type: ApiFieldType::RELATION,
            sortable: $sortable,
            filterable: true,
            relationName: $name,
            relationField: $relationField
        );

        return $this;
    }

    /**
     * Mark fields as sortable.
     */
    public function sortable(string ...$fields): self
    {
        $this->sorts = array_merge($this->sorts, $fields);

        return $this;
    }

    /**
     * Mark fields as filterable.
     */
    public function filterable(string ...$fields): self
    {
        $this->filters = array_merge($this->filters, $fields);

        return $this;
    }

    /**
     * Mark fields as searchable.
     */
    public function searchable(string ...$fields): self
    {
        $this->search['fields'] = array_merge($this->search['fields'], $fields);

        return $this;
    }

    /**
     * Add allowed includes.
     */
    public function includes(string ...$relations): self
    {
        $this->includes = array_merge($this->includes, $relations);

        return $this;
    }

    /**
     * Set the search strategy.
     */
    public function searchStrategy(string $strategy): self
    {
        $this->search['strategy'] = $strategy;

        return $this;
    }

    /**
     * Enable full-text search on specified fields.
     */
    public function fullTextSearch(string ...$fields): self
    {
        $this->search['strategy'] = 'full_text';
        $this->search['fields'] = array_merge($this->search['fields'], $fields);

        return $this;
    }

    /**
     * Add a media collection.
     *
     * @param  array<string>  $mimes
     * @param  array<string>  $conversions
     */
    public function mediaCollection(
        string $name,
        int $maxFiles = 10,
        array $mimes = [],
        array $conversions = []
    ): self {
        $this->mediaCollections[$name] = [
            'max_files' => $maxFiles,
            'accepted_mimes' => $mimes,
            'conversions' => $conversions,
        ];

        return $this;
    }

    /**
     * Set pagination settings.
     */
    public function pagination(int $default = 25, int $max = 100): self
    {
        $this->pagination = [
            'default_size' => $default,
            'max_size' => $max,
        ];

        return $this;
    }

    /**
     * Build and return the API structure array.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $structure = [
            'label' => $this->label,
            'slug' => $this->slug,
            'fields' => array_map(fn (ApiField $field) => $field->toArray(), $this->fields),
        ];

        if (! empty($this->filters)) {
            $structure['filters'] = array_values(array_unique($this->filters));
        }

        if (! empty($this->sorts)) {
            $structure['sorts'] = array_values(array_unique($this->sorts));
        }

        if (! empty($this->includes)) {
            $structure['allowed_includes'] = array_values(array_unique($this->includes));
        }

        if (! empty($this->search['fields'])) {
            $structure['search'] = [
                'strategy' => $this->search['strategy'],
                'fields' => array_values(array_unique($this->search['fields'])),
            ];
        }

        if (! empty($this->mediaCollections)) {
            $structure['media_collections'] = $this->mediaCollections;
        }

        if (! empty($this->pagination)) {
            $structure['pagination'] = $this->pagination;
        }

        return $structure;
    }
}
