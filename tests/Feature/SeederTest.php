<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

it('seeds an admin and a demo user that can log in', function (string $email, string $role) {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', $email)->first()->hasRole($role))->toBeTrue();

    Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => config('seed.'.$role.'_password')])
        ->call('authenticate')
        ->assertHasNoFormErrors();
})->with([
    ['admin@example.test', 'admin'],
    ['demo@example.test', 'demo'],
]);

it('refuses to seed in production', function () {
    app()->detectEnvironment(fn () => 'production');

    (new DatabaseSeeder)->run();

    expect(User::count())->toBe(0);
});
