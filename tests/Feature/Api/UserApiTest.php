<?php

use App\Models\User;

test('authenticated user can get their profile via API', function () {
    $user = User::factory()->create(['account_type' => 'student']);

    $this->actingAs($user)
         ->getJson('/api/v1/user')
         ->assertOk()
         ->assertJsonStructure([
             'data' => ['id', 'name', 'email', 'account_type', 'roles'],
         ])
         ->assertJsonPath('data.account_type', 'student');
});

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});
