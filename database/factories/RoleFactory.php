<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->jobTitle();

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => $this->faker->sentence(),
            'level' => $this->faker->numberBetween(1, 10),
            'scope' => $this->faker->randomElement([
                'global',
                'it-software',
                'it-infra',
                'hrd',
                'map',
                null
            ]),
            'can_approve' => $this->faker->boolean(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Custom States (Recommended)
    |--------------------------------------------------------------------------
    */

    public function superAdmin()
    {
        return $this->state(fn() => [
            'slug' => 'super-admin',
            'name' => 'Super Admin',
            'description' => 'Memiliki akses penuh ke seluruh sistem tanpa batasan scope dan dapat mengatur semua role serta konfigurasi.',
            'level' => 100,
            'scope' => 'global',
            'can_approve' => true,
        ]);
    }

    public function gm()
    {
        return $this->state(fn() => [
            'slug' => 'gm',
            'name' => 'General Manager',
            'description' => 'Pimpinan tertinggi operasional yang dapat menyetujui seluruh pengajuan dari semua divisi.',
            'level' => 90,
            'scope' => 'global',
            'can_approve' => true,
        ]);
    }

    public function manager()
    {
        return $this->state(fn() => [
            'slug' => 'manager',
            'name' => 'Manager',
            'description' => 'Mengelola divisi dan dapat menyetujui pengajuan dari Assistant Manager dan Supervisor dalam scope divisinya.',
            'level' => 80,
            'scope' => 'it',
            'can_approve' => true,
        ]);
    }

    public function asmen()
    {
        return $this->state(fn() => [
            'slug' => 'asmen',
            'name' => 'Assistant Manager',
            'description' => 'Membantu Manager dan dapat menyetujui pengajuan dari Supervisor dalam scope divisinya.',
            'level' => 70,
            'scope' => 'it',
            'can_approve' => true,
        ]);
    }

    public function spvInfra()
    {
        return $this->state(fn() => [
            'slug' => 'spv-it-infra',
            'name' => 'SPV IT Infra',
            'description' => 'Supervisor divisi IT Infrastruktur yang mengawasi staff IT Infra dan dapat menyetujui pengajuan dari staff dalam scope IT Infra.',
            'level' => 60,
            'scope' => 'it-infra',
            'can_approve' => true,
        ]);
    }

    public function spvSoftware()
    {
        return $this->state(fn() => [
            'slug' => 'spv-it-software',
            'name' => 'SPV IT Software',
            'description' => 'Supervisor divisi IT Software yang mengawasi staff IT Software dan dapat menyetujui pengajuan dari staff dalam scope IT Software.',
            'level' => 60,
            'scope' => 'it-software',
            'can_approve' => true,
        ]);
    }

    public function staff($scope = 'it-software')
    {
        return $this->state(fn() => [
            'slug' => 'staff-' . $scope,
            'name' => 'Staff ' . strtoupper(str_replace('-', ' ', $scope)),
            'description' => 'Staff operasional dalam divisi ' . strtoupper(str_replace('-', ' ', $scope)) . ' yang dapat membuat pengajuan namun tidak memiliki hak approval.',
            'level' => 50,
            'scope' => $scope,
            'can_approve' => false,
        ]);
    }

    public function viewer($scope = 'hrd')
    {
        return $this->state(fn() => [
            'slug' => $scope,
            'name' => strtoupper($scope),
            'description' => "Role view-only untuk divisi " . strtoupper($scope) . ' yang hanya dapat melihat data tanpa hak edit maupun approval.',
            'level' => 10,
            'scope' => $scope,
            'can_approve' => false,
        ]);
    }
}
