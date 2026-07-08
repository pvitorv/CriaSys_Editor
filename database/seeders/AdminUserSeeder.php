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
            throw new \RuntimeException(
                'ADMIN_PASSWORD não definida no .env. Copie .env.example → .env e preencha ADMIN_PASSWORD com a senha do SEU admin.'
            );
        }

        $email = env('ADMIN_EMAIL');
        if (! $email) {
            throw new \RuntimeException(
                'ADMIN_EMAIL não definida no .env. Use o e-mail do administrador desta máquina (não o do mantenedor do repositório).'
            );
        }

        $admin = User::updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'UserDev')],
            [
                'name' => 'Desenvolvedor',
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        Project::whereNull('user_id')->update(['user_id' => $admin->id]);
    }
}
