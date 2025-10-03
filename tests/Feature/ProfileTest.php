<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\{actingAs, putJson, getJson};

it('updates name and bio', function () {
    $user = User::factory()->create();

    actingAs($user)->putJson('/api/profile', [
        'name' => 'New Name',
        'bio' => 'Updated bio',
    ])->assertStatus(200);

    expect($user->fresh()->name)->toBe('New Name');
});

it('uploads a profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = actingAs($user)->putJson('/api/profile/photo', [
        'photo' => UploadedFile::fake()->image('profile.jpg'),
    ]);

    $response->assertStatus(200);
    Storage::disk('public')->assertExists("profile-photos/{$user->id}.jpg");
});

it('shows public profile info', function () {
    $user = User::factory()->create([
        'name' => 'Public User',
        'bio' => 'Visible bio',
    ]);
    
    actingAs($user); // 👈 Authenticate the request

    $response = getJson("/api/users/{$user->id}");

    $response->assertStatus(200)
             ->assertJsonPath('name', 'Public User')
             ->assertJsonPath('bio', 'Visible bio');
});
