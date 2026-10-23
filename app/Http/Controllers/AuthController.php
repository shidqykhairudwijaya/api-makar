<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new seller or buyer account along with their profile.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->validated('name'),
                'phone' => $request->validated('phone'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'role' => $request->validated('role'),
                'is_active' => true,
            ]);

            $user->profile()->create([
                'store_name' => $request->validated('store_name'),
                'market_name' => $request->validated('market_name'),
                'block_number' => $request->validated('block_number'),
                'full_address' => $request->validated('full_address'),
                'latitude' => $request->validated('latitude'),
                'longitude' => $request->validated('longitude'),
            ]);

            return $user;
        });

        $user->load('profile');
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Authenticate user with phone number and password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->validated('phone'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'message' => 'Nomor HP atau password salah.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;
        $user->load('profile');

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Revoke currently active Sanctum access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Retrieve the currently authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->load('profile')),
        ]);
    }
}
