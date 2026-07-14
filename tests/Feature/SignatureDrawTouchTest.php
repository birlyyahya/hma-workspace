<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    Http::fake(['*/global/user/get-user/*' => Http::response(['success' => true, 'data' => ['signature' => 'sig.png']], 200)]);
});

test('the draw signature pad forwards touch input to mouse events for mobile drawing', function () {
    Volt::actingAs(User::factory()->create())
        ->test('settings.signature')
        ->assertSeeHtml('enableTouchDrawing')
        ->assertSeeHtml("addEventListener('touchstart'")
        ->assertSeeHtml("addEventListener('touchmove'")
        ->assertSeeHtml("addEventListener('touchend'");
});

test('the signature pad disables native touch scrolling so strokes are not hijacked', function () {
    Volt::actingAs(User::factory()->create())
        ->test('settings.signature')
        ->assertSeeHtml('touch-action: none');
});
