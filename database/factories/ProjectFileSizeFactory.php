<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectFileSize>
 */
class ProjectFileSizeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fake()->numberBetween(1, 100),
            'doc_id' => fake()->unique()->numberBetween(1, 100000),
            'size_bytes' => fake()->numberBetween(1024, 50 * 1024 * 1024),
        ];
    }
}
