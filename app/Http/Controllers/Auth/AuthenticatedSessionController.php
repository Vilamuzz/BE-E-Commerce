<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\JsonResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request using JWT.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = JWTAuth::user();

        return ApiResponse::NewResponse(
            200,
            'Logged in successfully',
            [
                'user' => [
                    'id_user' => $user->id_user,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ],
        );
    }

    /**
     * Destroy an authenticated session (JWT logout).
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ApiResponse::NewResponse(
                200,
                'Successfully logged out',
                []
            );
        } catch (\Exception $e) {
            return ApiResponse::NewResponse(
                400,
                'Failed to logout, token invalid',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
