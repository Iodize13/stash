<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function userWithRole(?string $role): User
{
    $user = User::factory()->create();

    if ($role) {
        $user->assignRole(Role::findOrCreate($role));
    }

    return $user;
}

it('redirects guests to the admin login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('shows the dashboard to admin and demo users', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertOk();
})->with(['admin', 'demo']);

it('denies panel access to users without a role', function () {
    $this->actingAs(userWithRole(null))->get('/admin')->assertForbidden();
});

it('logs in with valid credentials', function () {
    $user = userWithRole('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = userWithRole('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'wrong'])
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    $this->assertGuest();
});
