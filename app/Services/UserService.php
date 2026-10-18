<?php

namespace App\Services;

use App\DTOs\UserData;
use App\Models\FinanceTransaction;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function createUser(UserData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data->name,
                'username' => $data->username,
                'email' => $data->email,
                'role' => $data->role,
                'profile_photo_path' => $data->profile_photo_path,
                'password' => Hash::make($data->password),
            ]);

            Cache::forget('users_list_all');

            return $user;
        });
    }

    public function updateUser(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if ($user->role === 'admin' && $data->role !== 'admin' && User::where('role', 'admin')->whereKeyNot($user->id)->doesntExist()) {
                throw ValidationException::withMessages(['role' => 'At least one admin user is required.']);
            }

            $oldPhotoPath = $user->profile_photo_path;

            $updateData = [
                'name' => $data->name,
                'username' => $data->username,
                'email' => $data->email,
                'role' => $data->role,
                'profile_photo_path' => $data->profile_photo_path ?? $user->profile_photo_path,
            ];

            if ($data->password) {
                $updateData['password'] = Hash::make($data->password);
            }

            $user->update($updateData);

            if ($data->profile_photo_path && $oldPhotoPath && $oldPhotoPath !== $data->profile_photo_path) {
                Storage::disk('public')->delete($oldPhotoPath);
            }

            Cache::forget('users_list_all');

            return $user->fresh();
        });
    }

    public function deleteUser(User $user): void
    {
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->whereKeyNot($user->id)->doesntExist()) {
            throw ValidationException::withMessages(['user' => 'At least one admin user is required.']);
        }

        if ($user->sales()->exists()) {
            throw ValidationException::withMessages(['user' => 'Cannot delete user who has recorded sales.']);
        }

        if (Purchase::where('created_by', $user->id)->exists()) {
            throw ValidationException::withMessages(['user' => 'Cannot delete user who has recorded purchases.']);
        }

        if (FinanceTransaction::where('created_by', $user->id)->exists()) {
            throw ValidationException::withMessages(['user' => 'Cannot delete user who has recorded finance transactions.']);
        }

        $photoPath = $user->profile_photo_path;

        $user->delete();

        if ($photoPath) {
            Storage::disk('public')->delete($photoPath);
        }

        Cache::forget('users_list_all');
    }
}
