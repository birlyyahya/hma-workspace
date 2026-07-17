<?php

namespace Database\Factories;

use App\Models\ProjectFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectFolderFile>
 */
class ProjectFolderFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fake()->numberBetween(1, 100),
            'doc_id' => fake()->unique()->numberBetween(1, 100000),
            'project_folder_id' => ProjectFolder::factory(),
        ];
    }
}
