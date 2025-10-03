<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CollaborationRequest;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(5)->create();
        CollaborationRequest::factory(10)->create();
    }
}
