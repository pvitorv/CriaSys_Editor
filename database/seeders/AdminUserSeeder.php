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
        $password = env('ADMIN_PASSWORD');
        if (! $password) {
            throw new \RuntimeException('ADMIN_PASSWORD não definida no .env');
        }

        $admin = User::updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'UserDev')],
            [
                'name' => 'Desenvolvedor',
                'email' => env('ADMIN_EMAIL', 'pontodeimpacto790@gmail.com'),
                'password' => Hash::make($password),
                'is_admin' => true,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        Project::whereNull('user_id')->update(['user_id' => $admin->id]);
    }
}
