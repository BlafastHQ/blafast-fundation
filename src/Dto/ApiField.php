<?php

declare(strict_types=1);

namespace Blafast\Foundation\Dto;

use Blafast\Foundation\Enums\ApiFieldType;

/**
 * Data Transfer Object for API field definitions.
 */
readonly class ApiField
{
    public function __construct(
        public string $name,
        public string $label,
        public ApiFieldType $type,
        public bool $sortable = false,
        public bool $filterable = false,
        public bool $searchable = false,
        public ?string $cast = null,
        public ?string $relationName = null,
        public ?string $relationField = null,
        public ?array $enumValues = null,
        public ?int $decimalPlaces = null,
    ) {}

    /**
     * Convert the field to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'sortable' => $this->sortable,
            'filterable' => $this->filterable,
            'searchable' => $this->searchable,
        ];

        if ($this->cast !== null) {
            $data['cast'] = $this->cast;
        }

        if ($this->type === ApiFieldType::RELATION) {
            $data['relation_name'] = $this->relationName;
            $data['relation_field'] = $this->relationField;
        }

        if ($this->type === ApiFieldType::DECIMAL && $this->decimalPlaces !== null) {
            $data['type'] = "decimal:{$this->decimalPlaces}";
        }

        if ($this->type === ApiFieldType::ENUM && $this->enumValues !== null) {
            $data['enum_values'] = $this->enumValues;
        }

        return $data;
    }

    /**
     * Create a new instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            label: $data['label'],
            type: ApiFieldType::from($data['type']),
            sortable: $data['sortable'] ?? false,
            filterable: $data['filterable'] ?? false,
            searchable: $data['searchable'] ?? false,
            cast: $data['cast'] ?? null,
            relationName: $data['relation_name'] ?? null,
            relationField: $data['relation_field'] ?? null,
            enumValues: $data['enum_values'] ?? null,
            decimalPlaces: $data['decimal_places'] ?? null,
        );
    }
}
