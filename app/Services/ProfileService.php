<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Update user profile information.
     *
     * @param  array{name: string, email: string}  $data
     *
     * @throws Exception
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            try {
                // Check for email or username collisions
                if (
                    $user->email !== $data['email'] &&
                    User::where('email', $data['email'])->exists()
                ) {
                    throw new Exception('The email address is already in use by another account.');
                }

                if (
                    $user->username !== $data['username'] &&
                    User::where('username', $data['username'])->exists()
                ) {
                    throw new Exception('The username is already taken by another account.');
                }

                $oldPhotoPath = $user->profile_photo_path;

                $updates = [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'profile_photo_path' => $data['profile_photo_path'] ?? $user->profile_photo_path,
                ];

                if ($user->email !== $data['email']) {
                    $updates['email_verified_at'] = null;
                }

                $user->update($updates);

                if (! empty($data['profile_photo_path']) && $oldPhotoPath && $oldPhotoPath !== $data['profile_photo_path']) {
                    Storage::disk('public')->delete($oldPhotoPath);
                }

                Log::info("User profile updated: {$user->id}");

                return $user;

            } catch (Exception $e) {
                Log::error("Failed to update profile for user {$user->id}: ".$e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Update user password.
     *
     * @throws Exception
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): void
    {
        DB::transaction(function () use ($user, $currentPassword, $newPassword) {
            try {
                if (! Hash::check($currentPassword, $user->password)) {
                    throw new Exception('The provided current password does not match your password.');
                }

                $user->update([
                    'password' => Hash::make($newPassword),
                ]);

                Log::info("User password updated: {$user->id}");

            } catch (Exception $e) {
                Log::error("Failed to update password for user {$user->id}: ".$e->getMessage());
                throw $e;
            }
        });
    }
}
