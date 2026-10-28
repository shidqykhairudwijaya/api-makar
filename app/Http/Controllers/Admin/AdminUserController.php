<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * Display a paginated listing of marketplace users with role and status filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('profile')
            ->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->has('is_active') && $request->query('is_active') !== null) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        if ($request->filled('search')) {
            $search = strtolower((string) $request->query('search'));
            $query->where(function ($q) use ($search): void {
                $q->where(DB::raw('LOWER(name)'), 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere(DB::raw('LOWER(email)'), 'like', "%{$search}%")
                    ->orWhereHas('profile', fn ($pq) => $pq->where(DB::raw('LOWER(store_name)'), 'like', "%{$search}%")
                        ->orWhere(DB::raw('LOWER(market_name)'), 'like', "%{$search}%"));
            });
        }

        $perPage = $request->integer('per_page', 15);
        $users = $query->paginate($perPage);

        return UserResource::collection($users)->response();
    }

    /**
     * Display details of a specific user including stall/warung profile.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('profile');

        return (new UserResource($user))->response();
    }

    /**
     * Activate or deactivate a user account with automatic session token revocation upon deactivation.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $isActive = (bool) $request->validated('is_active');

        if ($user->id === $admin->id && ! $isActive) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun administrator Anda sendiri.',
            ], 422);
        }

        DB::transaction(function () use ($user, $isActive): void {
            $user->update(['is_active' => $isActive]);

            if (! $isActive) {
                // Immediately revoke all active personal access tokens
                $user->tokens()->delete();
            }
        });

        $user->load('profile');

        return response()->json([
            'message' => 'Status akun pengguna berhasil diperbarui.',
            'data' => new UserResource($user),
        ]);
    }
}
