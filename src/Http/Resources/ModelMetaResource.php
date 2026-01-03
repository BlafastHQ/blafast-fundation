<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Resources;

use Blafast\Foundation\Dto\ModelMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON:API resource for model metadata.
 *
 * Formats model metadata according to JSON:API specification.
 *
 * @property ModelMeta $resource
 */
class ModelMetaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'model-meta',
            'id' => $this->resource->slug,
            'attributes' => $this->when(
                true,
                [
                    'model' => $this->resource->model,
                    'label' => $this->resource->label,
                    'endpoints' => $this->resource->endpoints,
                    'fields' => $this->resource->fields,
                    'filters' => $this->buildFilters($this->resource->fields),
                    'sorts' => $this->buildSorts($this->resource->fields),
                    'allowed_includes' => $this->resource->search['allowed_includes'] ?? [],
                    'methods' => $this->when(! empty($this->resource->methods), $this->resource->methods),
                    'media_collections' => $this->when(! empty($this->resource->mediaCollections), $this->resource->mediaCollections),
                    'search' => $this->when($this->resource->search !== null, $this->resource->search),
                    'pagination' => $this->when($this->resource->pagination !== null, $this->resource->pagination),
                ]
            ),
        ];
    }

    /**
     * Build filters array from fields.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function buildFilters(array $fields): array
    {
        return array_values(array_filter(
            array_map(function ($field) {
                if (! ($field['filterable'] ?? false)) {
                    return null;
                }

                return [
                    'field' => $field['name'],
                    'type' => $field['type'],
                ];
            }, $fields)
        ));
    }

    /**
     * Build sorts array from fields.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    private function buildSorts(array $fields): array
    {
        return array_values(array_filter(
            array_map(function ($field) {
                return ($field['sortable'] ?? false) ? $field['name'] : null;
            }, $fields)
        ));
    }
}
