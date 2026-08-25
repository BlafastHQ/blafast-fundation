<?php

declare(strict_types=1);

namespace Blafast\Foundation\Contracts;

interface HasApiStructure
{
    /**
     * Define the API structure for this model.
     *
     * @return array{
     *     label: string,
     *     slug: string,
     *     fields: array<int, array{
     *         name: string,
     *         label: string,
     *         type: string,
     *         sortable?: bool,
     *         filterable?: bool,
     *         searchable?: bool,
     *         cast?: string,
     *         relation_name?: string,
     *         relation_field?: string,
     *         enum_values?: array<string>,
     *         decimal_places?: int
     *     }>,
     *     filters?: array<string>,
     *     sorts?: array<string>,
     *     allowed_includes?: array<string>,
     *     search?: array{strategy: string, fields: array<string>},
     *     media_collections?: array<string, array{max_files?: int, accepted_mimes?: array<string>, conversions?: array<string>}>,
     *     pagination?: array{default_size?: int, max_size?: int}
     * }
     */
    public static function apiStructure(): array;

    /**
     * The model's canonical API slug — the single source for routes, permission
     * names, exec checks and metadata (task 7). Provided by ExposesApiStructure.
     */
    public static function getApiSlug(): string;

    /**
     * The compiled (cached) API structure the runtime consumes — provided by
     * ExposesApiStructure (task 14).
     *
     * @return array<string, mixed>
     */
    public static function getApiStructure(): array;

    /**
     * The allowed include names — provided by ExposesApiStructure (task 14).
     *
     * @return array<int, string>
     */
    public static function getApiIncludes(): array;
}
