<?php

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Analysis> */
class AnalysisFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'title' => 'Test analysis', 'origin' => 'paste', 'status' => Analysis::COMPLETED];
    }
}
