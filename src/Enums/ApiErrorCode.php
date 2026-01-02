<?php

declare(strict_types=1);

namespace Blafast\Foundation\Enums;

/**
 * API Error Codes
 *
 * Standard error codes following JSON:API specification for consistent error handling.
 */
enum ApiErrorCode: string
{
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case AUTHENTICATION_REQUIRED = 'AUTHENTICATION_REQUIRED';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case TOKEN_INVALID = 'TOKEN_INVALID';
    case ACCESS_DENIED = 'ACCESS_DENIED';
    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    case MISSING_ORGANIZATION = 'MISSING_ORGANIZATION';
    case ORGANIZATION_REQUIRED = 'ORGANIZATION_REQUIRED';
    case ORGANIZATION_ACCESS_DENIED = 'ORGANIZATION_ACCESS_DENIED';
    case MEMBERSHIP_INACTIVE = 'MEMBERSHIP_INACTIVE';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    case EXEC_PERMISSION_DENIED = 'EXEC_PERMISSION_DENIED';
    case CONFLICT = 'CONFLICT';
    case UNPROCESSABLE_ENTITY = 'UNPROCESSABLE_ENTITY';
    case BAD_REQUEST = 'BAD_REQUEST';

    /**
     * Get the human-readable title for this error code.
     */
    public function title(): string
    {
        return match ($this) {
            self::VALIDATION_ERROR => 'Validation Failed',
            self::AUTHENTICATION_REQUIRED => 'Authentication Required',
            self::INVALID_CREDENTIALS => 'Invalid Credentials',
            self::TOKEN_EXPIRED => 'Token Expired',
            self::TOKEN_INVALID => 'Invalid Token',
            self::ACCESS_DENIED => 'Access Denied',
            self::RESOURCE_NOT_FOUND => 'Resource Not Found',
            self::MISSING_ORGANIZATION => 'Organization Required',
            self::ORGANIZATION_REQUIRED => 'Organization Context Required',
            self::ORGANIZATION_ACCESS_DENIED => 'Organization Access Denied',
            self::MEMBERSHIP_INACTIVE => 'Membership Inactive',
            self::RATE_LIMIT_EXCEEDED => 'Rate Limit Exceeded',
            self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::INTERNAL_ERROR => 'Internal Server Error',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::EXEC_PERMISSION_DENIED => 'Execution Permission Denied',
            self::CONFLICT => 'Resource Conflict',
            self::UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
            self::BAD_REQUEST => 'Bad Request',
        };
    }

    /**
     * Get the default HTTP status code for this error.
     */
    public function defaultStatus(): int
    {
        return match ($this) {
            self::VALIDATION_ERROR, self::UNPROCESSABLE_ENTITY => 422,
            self::AUTHENTICATION_REQUIRED, self::INVALID_CREDENTIALS, self::TOKEN_EXPIRED, self::TOKEN_INVALID => 401,
            self::ACCESS_DENIED, self::ORGANIZATION_ACCESS_DENIED, self::EXEC_PERMISSION_DENIED, self::MEMBERSHIP_INACTIVE => 403,
            self::RESOURCE_NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::CONFLICT => 409,
            self::RATE_LIMIT_EXCEEDED => 429,
            self::SERVICE_UNAVAILABLE => 503,
            self::MISSING_ORGANIZATION, self::ORGANIZATION_REQUIRED, self::BAD_REQUEST => 400,
            self::INTERNAL_ERROR => 500,
        };
    }
}
