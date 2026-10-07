<?php

use App\Models\Collection;
use App\Models\User;
use Spatie\Permission\Models\Role;

function demoUser(): User
{
    return User::factory()->create()->assignRole(Role::findOrCreate('demo'));
}

it('explains the app to guests and offers the demo', function () {
    demoUser();

    $this->get('/')
        ->assertOk()
        ->assertSee('Try the demo')
        ->assertSee('// HOW IT WORKS')
        ->assertSee('SSRF-SAFE FETCHING')
        ->assertSee('ISOLATED SANDBOXES')
        ->assertDontSee('read-only');
});

it('links to a public collection but never a private one', function () {
    Collection::factory()->create(['slug' => 'private-notes']);
    $this->get('/')->assertDontSee('private-notes')->assertDontSee('See a public collection');

    Collection::factory()->public()->create(['slug' => 'reading-list']);
    $this->get('/')->assertSee('/c/reading-list');
});

it('hides the demo button when the demo is disabled', function () {
    config(['stash.demo_login' => false]);

    $this->get('/')->assertDontSee('Try the demo');
    $this->post('/demo')->assertNotFound();
    $this->assertGuest();
});

it('sends signed-in users to their library instead', function () {
    $user = demoUser();

    $this->actingAs($user)->get('/')->assertSee('Open your library')->assertDontSee('Try the demo');
    $this->actingAs($user)->post('/demo')->assertRedirect();
});

it('links the public collection page back to the landing page', function () {
    Collection::factory()->public()->create(['slug' => 'reading-list']);

    $this->get('/c/reading-list')->assertSee('See how it works');
});
