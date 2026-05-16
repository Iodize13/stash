<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusing to seed in production.');

            return;
        }

        $accounts = [
            ['admin', 'Admin', 'admin@example.test', config('seed.admin_password')],
            ['demo', 'Demo', 'demo@example.test', config('seed.demo_password')],
        ];

        foreach ($accounts as [$role, $name, $email, $password]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password],
            );

            $user->syncRoles(Role::findOrCreate($role));
        }
    }
}
