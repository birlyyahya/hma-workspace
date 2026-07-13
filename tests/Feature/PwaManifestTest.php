<?php

test('the pwa manifest file is valid and installable', function () {
    $path = public_path('manifest.json');

    expect(file_exists($path))->toBeTrue();

    $manifest = json_decode(file_get_contents($path), true);

    expect($manifest)->toBeArray()
        ->and($manifest['name'])->toBe('HMA Workspace')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path($icon['src'])))->toBeTrue();
    }
});

test('pages link the manifest and ios web app meta tags', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('manifest.json')
        ->assertSee('apple-mobile-web-app-capable', false);
});
