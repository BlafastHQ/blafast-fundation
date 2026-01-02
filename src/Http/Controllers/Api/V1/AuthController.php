<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Http\Requests\Auth\CreateTokenRequest;
use Blafast\Foundation\Http\Requests\Auth\LoginRequest;
use Blafast\Foundation\Http\Resources\TokenResource;
use Blafast\Foundation\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue access token.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $userModel = config('auth.providers.users.model');

        $user = $userModel::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => [[
                    'status' => '401',
                    'code' => 'INVALID_CREDENTIALS',
                    'title' => 'Authentication Failed',
                    'detail' => 'The provided credentials are incorrect.',
                ]],
            ], 401);
        }

        // Create token with all abilities
        $token = $user->createToken($request->device_name, ['*']);

        $resource = new TokenResource($token->accessToken);
        $resource->plainTextToken = $token->plainTextToken;

        return response()->json([
            'data' => $resource->toArray($request),
        ], 201);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @phpstan-ignore-next-line */
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'data' => [
                'type' => 'auth-logout',
                'attributes' => [
                    'message' => 'Token successfully revoked.',
                ],
            ],
        ], 200);
    }

    /**
     * Revoke all user's access tokens.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @phpstan-ignore-next-line */
        $request->user()->tokens()->delete();

        return response()->json([
            'data' => [
                'type' => 'auth-logout',
                'attributes' => [
                    'message' => 'All tokens successfully revoked.',
                ],
            ],
        ], 200);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new UserResource($request->user()))->toArray($request),
        ], 200);
    }

    /**
     * List all user's access tokens.
     */
    public function tokens(Request $request): JsonResponse
    {
        /** @phpstan-ignore-next-line */
        $tokens = $request->user()->tokens()->get();

        return response()->json([
            'data' => TokenResource::collection($tokens)->toArray($request),
        ], 200);
    }

    /**
     * Create a new access token.
     */
    public function createToken(CreateTokenRequest $request): JsonResponse
    {
        $abilities = $request->input('abilities', ['*']);

        /** @phpstan-ignore-next-line */
        $token = $request->user()->createToken(
            $request->name,
            $abilities,
            $request->expires_at ? now()->parse($request->expires_at) : null
        );

        $resource = new TokenResource($token->accessToken);
        $resource->plainTextToken = $token->plainTextToken;

        return response()->json([
            'data' => $resource->toArray($request),
        ], 201);
    }

    /**
     * Revoke a specific access token.
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        /** @phpstan-ignore-next-line */
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            return response()->json([
                'errors' => [[
                    'status' => '404',
                    'code' => 'TOKEN_NOT_FOUND',
                    'title' => 'Token Not Found',
                    'detail' => 'The specified token does not exist or does not belong to you.',
                ]],
            ], 404);
        }

        $token->delete();

        return response()->json([
            'data' => [
                'type' => 'auth-token-revoke',
                'attributes' => [
                    'message' => 'Token successfully revoked.',
                ],
            ],
        ], 200);
    }
}
