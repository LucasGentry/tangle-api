<?php
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\deleteJson;

it('follows another user', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($me);

    $response = postJson("/api/users/{$target->id}/follow");

    $response->assertStatus(200)
             ->assertJson(['message' => 'Followed']);
});

it('cannot follow self', function () {
    $me = User::factory()->create();

    Sanctum::actingAs($me);

    $response = postJson("/api/users/{$me->id}/follow");

    $response->assertStatus(422);
});

it('unfollows a user', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();

    $me->follows()->attach($target);

    Sanctum::actingAs($me);

    $response = deleteJson("/api/users/{$target->id}/unfollow");

    $response->assertStatus(200)
             ->assertJson(['message' => 'Unfollowed']);
});
