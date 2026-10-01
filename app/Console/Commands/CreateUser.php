<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Creates (or updates) an admin or demo user. Production has no seeder, so this
 * is how the first accounts are made on a fresh deploy.
 */
class CreateUser extends Command
{
    protected $signature = 'stash:create-user
        {email}
        {--name= : Display name (defaults to the part of the email before @)}
        {--role=admin : admin or demo}
        {--password= : Omit to generate one and print it}';

    protected $description = 'Create or update an admin or demo user';

    public function handle(): int
    {
        $role = $this->option('role');

        if (! in_array($role, ['admin', 'demo'], true)) {
            $this->error('--role must be admin or demo.');

            return self::INVALID;
        }

        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Not a valid email address.');

            return self::INVALID;
        }

        $password = $this->option('password') ?: Str::password(20, symbols: false);

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name') ?: Str::before($email, '@'), 'password' => $password],
        );
        $user->syncRoles(Role::findOrCreate($role));

        $this->info("{$role} user {$email} is ready.");

        if (! $this->option('password')) {
            $this->line("Password: {$password}");
        }

        return self::SUCCESS;
    }
}
