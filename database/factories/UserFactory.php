<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= \Illuminate\Support\Facades\Hash::make('password'),
            'remember_token' => \Illuminate\Support\Str::random(10),
            'location' => fake()->optional()->city(),
            'bio' => fake()->optional()->paragraph(),
            'profile_photo' => '/storage/profile-photos/' . fake()->uuid() . '.webp',
            'portfolio_images' => array_map(function () {
                $uuid = fake()->uuid();
                return [
                    'path' => 'portfolio/' . $uuid . '.webp',
                    'url' => '/storage/portfolio/' . $uuid . '.webp',
                ];
            }, range(1, fake()->numberBetween(1, 3))),
            'social_links' => [
                'twitter' => fake()->optional()->url(),
                'facebook' => fake()->optional()->url(),
                'instagram' => fake()->optional()->url(),
            ],
            'is_verified' => fake()->boolean(),
            'social_media' => [
                [
                    'platform' => fake()->randomElement(['Instagram', 'YouTube', 'TikTok', 'Twitter', 'Facebook']),
                    'handle' => '@' . fake()->userName(),
                ]
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
