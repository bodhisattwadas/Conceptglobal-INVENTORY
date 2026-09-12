<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'admin',
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'role' => 'admin',
            'profile_photo_path' => null,
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'Store Manager',
            'username' => 'manager',
            'email' => 'manager@example.com',
            'role' => 'manager',
            'profile_photo_path' => null,
            'password' => Hash::make('password'),
        ]);

        User::create([
            'name' => 'Sales Staff',
            'username' => 'staff',
            'email' => 'staff@example.com',
            'role' => 'sales',
            'profile_photo_path' => null,
            'password' => Hash::make('password'),
        ]);
    }
}
