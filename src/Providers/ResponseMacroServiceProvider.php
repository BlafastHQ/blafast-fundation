<?php

declare(strict_types=1);

namespace Blafast\Foundation\Providers;

use Blafast\Foundation\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

/**
 * Response Macro Service Provider
 *
 * Registers JSON:API response macros for consistent API responses.
 */
class ResponseMacroServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerJsonApiErrorMacro();
        $this->registerJsonApiSuccessMacro();
        $this->registerJsonApiCollectionMacro();
    }

    /**
     * Register the jsonApiError response macro.
     */
    private function registerJsonApiErrorMacro(): void
    {
        Response::macro('jsonApiError', function (
            ApiErrorCode $code,
            string $detail,
            ?int $status = null,
            ?array $source = null,
            ?array $meta = null
        ): JsonResponse {
            $status = $status ?? $code->defaultStatus();

            $error = [
                'status' => (string) $status,
                'code' => $code->value,
                'title' => $code->title(),
                'detail' => $detail,
            ];

            if ($source) {
                $error['source'] = $source;
            }

            if ($meta) {
                $error['meta'] = $meta;
            }

            return response()->json(['errors' => [$error]], $status);
        });
    }

    /**
     * Register the jsonApiSuccess response macro.
     */
    private function registerJsonApiSuccessMacro(): void
    {
        Response::macro('jsonApiSuccess', function (
            mixed $data,
            ?array $meta = null,
            ?array $links = null,
            int $status = 200
        ): JsonResponse {
            $response = ['data' => $data];

            if ($meta) {
                $response['meta'] = $meta;
            }

            if ($links) {
                $response['links'] = $links;
            }

            return response()->json($response, $status);
        });
    }

    /**
     * Register the jsonApiCollection response macro.
     */
    private function registerJsonApiCollectionMacro(): void
    {
        Response::macro('jsonApiCollection', function (
            array $data,
            ?array $meta = null,
            ?array $links = null,
            int $status = 200
        ): JsonResponse {
            $response = ['data' => $data];

            if ($links) {
                $response['links'] = $links;
            }

            if ($meta) {
                $response['meta'] = $meta;
            }

            return response()->json($response, $status);
        });
    }
}
