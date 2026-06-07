<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ApiLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(ApiLoginRequest $request): JsonResponse
    {
        $result = $this->auth->loginWithToken(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'api')->toString(),
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], 'Logged in');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');
        $request->merge(['with_permissions' => true]);

        return ApiResponse::success(new UserResource($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out');
    }
}
