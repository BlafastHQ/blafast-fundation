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
}
