<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('dashboard page renders with the project activity widget', function () {
    Http::fake(['*' => Http::response(['data' => []])]);
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeLivewire('widget.dashboard.project-activity');
});
