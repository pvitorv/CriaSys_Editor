<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'UserDev')],
            [
                'name' => 'Desenvolvedor',
                'email' => env('ADMIN_EMAIL', 'userdev@criasys.local'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'UserDev@2026')),
                'is_admin' => true,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        Project::whereNull('user_id')->update(['user_id' => $admin->id]);
    }
}
