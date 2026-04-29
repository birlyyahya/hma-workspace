<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    Http::fake([
        '*' => Http::response(['data' => []]),
    ]);

    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('data-testid="dashboard-welcome-widget"', false)
        ->assertSee($user->name)
        ->assertSee('Tambah Proyek');
});
