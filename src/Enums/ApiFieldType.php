<?php

declare(strict_types=1);

namespace Blafast\Foundation\Enums;

enum ApiFieldType: string
{
    case UUID = 'uuid';
    case STRING = 'string';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case JSON = 'json';
    case RELATION = 'relation';
    case ENUM = 'enum';
    case TEXT = 'text';

    /**
     * Check if the field type is numeric.
     */
    public function isNumeric(): bool
    {
        return in_array($this, [self::INTEGER, self::FLOAT, self::DECIMAL]);
    }

    /**
     * Check if the field type is searchable by default.
     */
    public function isSearchable(): bool
    {
        return in_array($this, [self::STRING, self::UUID, self::TEXT]);
    }

    /**
     * Check if the field type is sortable by default.
     */
    public function isSortable(): bool
    {
        return in_array($this, [
            self::UUID,
            self::STRING,
            self::INTEGER,
            self::FLOAT,
            self::DECIMAL,
            self::DATE,
            self::DATETIME,
            self::BOOLEAN,
        ]);
    }

    /**
     * Check if the field type is filterable by default.
     */
    public function isFilterable(): bool
    {
        return in_array($this, [
            self::UUID,
            self::STRING,
            self::INTEGER,
            self::FLOAT,
            self::DECIMAL,
            self::DATE,
            self::DATETIME,
            self::BOOLEAN,
            self::ENUM,
        ]);
    }

    /**
     * Get the default cast for this field type.
     */
    public function getDefaultCast(): ?string
    {
        return match ($this) {
            self::INTEGER => 'integer',
            self::FLOAT => 'float',
            self::DECIMAL => 'decimal:2',
            self::BOOLEAN => 'boolean',
            self::DATE => 'date',
            self::DATETIME => 'datetime',
            self::JSON => 'array',
            default => null,
        };
    }
}
