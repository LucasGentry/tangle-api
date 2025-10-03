<?php

use App\Models\User;
use function Pest\Laravel\postJson;

it('registers a new user', function () {
    $response = postJson('/api/register', [
        'name' => 'Chris',
        'email' => 'chris@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['token']);
});

it('logs in a user', function () {
    User::factory()->create([
        'email' => 'chris@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = postJson('/api/login', [
        'email' => 'chris@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['token']);
});

it('fails login with bad credentials', function () {
    $response = postJson('/api/login', [
        'email' => 'wrong@example.com',
        'password' => 'wrongpass',
    ]);

    $response->assertStatus(401);
});
