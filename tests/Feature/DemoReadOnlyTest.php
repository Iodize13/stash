<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function panelUser(string $role): User
{
    return User::factory()->create()->assignRole(Role::findOrCreate($role));
}

it('shows demo users the list without write actions', function () {
    $record = User::factory()->create();

    Livewire::actingAs(panelUser('demo'))
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords([$record])
        ->assertActionHidden(TestAction::make('edit')->table($record))
        ->assertActionHidden('create');
});

it('returns 403 when a demo user opens the edit page', function () {
    $record = User::factory()->create();

    $this->actingAs(panelUser('demo'))
        ->get(UserResource::getUrl('edit', ['record' => $record]))
        ->assertForbidden();
});

it('returns 403 when a demo user opens the create page', function () {
    $this->actingAs(panelUser('demo'))
        ->get(UserResource::getUrl('create'))
        ->assertForbidden();
});

it('shows admins the write actions', function () {
    $record = User::factory()->create();

    Livewire::actingAs(panelUser('admin'))
        ->test(ListUsers::class)
        ->assertActionVisible(TestAction::make('edit')->table($record))
        ->assertActionVisible('create');
});
