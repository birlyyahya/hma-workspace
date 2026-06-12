<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('create forwards the selected type to the spectech API', function () {
    Http::fake([
        '*activity-categories' => Http::response([
            'status' => 201,
            'data' => [
                'id' => 99,
                'name' => 'Lisensi Office',
                'qty_total' => 2,
                'qty_recived' => 0,
                'total_nominal' => 2000000,
                'qty_nominal' => 1000000,
                'percentage' => 0,
                'note' => '',
                'images' => [],
                'type' => 'software',
            ],
        ], 201),
    ]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'spectech' => [],
        'id' => 1,
        'progress' => 0,
    ])
        ->set('form.type', 'software')
        ->set('form.name', 'Lisensi Office')
        ->set('form.quantity', 2)
        ->set('form.price', '2.000.000')
        ->call('create')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'activity-categories')
        && $request['type'] === 'software');
});

test('type is required and must be hardware or software', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'spectech' => [],
        'id' => 1,
        'progress' => 0,
    ])
        ->set('form.type', 'firmware')
        ->set('form.name', 'Item')
        ->set('form.quantity', 1)
        ->set('form.price', '1.000')
        ->call('create')
        ->assertHasErrors(['form.type']);
});
