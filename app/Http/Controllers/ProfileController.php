<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Retrieve the authenticated user's profile with detailed stall / store data.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('profile');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update the authenticated user's information and profile details.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $userAttributes = [];
            foreach (['name', 'phone', 'email'] as $field) {
                if ($request->has($field)) {
                    $userAttributes[$field] = $request->validated($field);
                }
            }

            if (! empty($userAttributes)) {
                $user->update($userAttributes);
            }

            $profileFields = ['store_name', 'market_name', 'block_number', 'full_address', 'latitude', 'longitude'];
            $profileAttributes = [];
            foreach ($profileFields as $field) {
                if ($request->has($field)) {
                    $profileAttributes[$field] = $request->validated($field);
                }
            }

            if (! empty($profileAttributes)) {
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileAttributes
                );
            }
        });

        $user->load('profile');

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}
