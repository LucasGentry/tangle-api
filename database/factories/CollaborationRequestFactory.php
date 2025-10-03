<?php

namespace Database\Factories;

use App\Models\CollaborationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CollaborationRequestFactory extends Factory
{
    protected $model = CollaborationRequest::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(6, true),
            'categories' => [$this->faker->word, $this->faker->word],
            'platforms' => [$this->faker->randomElement(['Instagram', 'YouTube', 'TikTok', 'Twitter', 'Facebook'])],
            'deadline' => $this->faker->optional()->dateTimeBetween('now', '+2 months'),
            'location_type' => $this->faker->optional()->randomElement(['Online', 'Offline', 'Hybrid']),
            'location' => $this->faker->optional()->city,
            'description' => $this->faker->optional()->paragraph,
            'colloborator_count' => $this->faker->numberBetween(1, 10),
            'collaboration_images' => array_map(function () {
                $url = $this->faker->imageUrl(640, 480, 'business', true, 'collab');
                return [
                    'path' => $this->faker->uuid . '.webp',
                    'url' => $url,
                    'size' => $this->faker->numberBetween(10000, 500000), // bytes
                    'mime_type' => 'image/webp',
                ];
            }, range(1, $this->faker->numberBetween(1, 3))),
            'application_fee' => $this->faker->optional()->randomFloat(2, 0, 100),
            'status' => $this->faker->randomElement(['Draft', 'Open', 'Reviewing Applicants', 'In Progress', 'Completed', 'Cancelled']),
            'cancellation_reason' => $this->faker->optional()->sentence,
            'share_token' => Str::random(16),
            'created_at' => $this->faker->dateTimeThisYear,
            'updated_at' => now(),
        ];
    }
} 