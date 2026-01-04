<?php

declare(strict_types=1);

namespace Blafast\Foundation\Enums;

/**
 * Enum for API method parameter types.
 *
 * Defines the supported parameter types for API methods
 * and provides validation rules for each type.
 */
enum ApiMethodParameterType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case EMAIL = 'email';
    case UUID = 'uuid';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case ARRAY = 'array';
    case JSON = 'json';
    case FILE = 'file';
    case ENUM = 'enum';

    /**
     * Get Laravel validation rule for this type.
     */
    public function validationRule(): string
    {
        return match ($this) {
            self::STRING => 'string',
            self::INTEGER => 'integer',
            self::FLOAT => 'numeric',
            self::BOOLEAN => 'boolean',
            self::EMAIL => 'email',
            self::UUID => 'uuid',
            self::DATE => 'date',
            self::DATETIME => 'date',
            self::ARRAY => 'array',
            self::JSON => 'json',
            self::FILE => 'file',
            self::ENUM => 'string',
        };
    }

    /**
     * Parse type string with modifiers (e.g., "array:email", "enum:a4,letter").
     *
     * @return array{type: self, modifier: string|null}
     */
    public static function parse(string $typeString): array
    {
        if (str_contains($typeString, ':')) {
            [$type, $modifier] = explode(':', $typeString, 2);

            return [
                'type' => self::from($type),
                'modifier' => $modifier,
            ];
        }

        return [
            'type' => self::from($typeString),
            'modifier' => null,
        ];
    }
}
