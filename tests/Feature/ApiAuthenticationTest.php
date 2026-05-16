<?php

use App\Models\User;

it('rejects a request without a token with a JSON 401', function () {
    $this->getJson('/api/user')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('rejects a request with an invalid token', function () {
    $this->withToken('invalid')->getJson('/api/user')->assertUnauthorized();
});

it('returns the token owner without sensitive fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/user')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]])
        ->assertJsonMissing(['password', 'remember_token']);
});
