<?php

use Livewire\Volt\Volt;

function breadcrumbItems(?string $routeName): array
{
    return Volt::test('components.breadcrumb', ['routeName' => $routeName])->get('items');
}

test('dashboard shows a single non-linked crumb', function () {
    expect(breadcrumbItems('dashboard'))->toBe([
        ['label' => 'Dashboard', 'url' => null],
    ]);
});

test('a top level page is prefixed with a linked dashboard crumb', function () {
    expect(breadcrumbItems('izin'))->toBe([
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Izin', 'url' => null],
    ]);
});

test('a child page links to its parent and keeps itself unlinked', function () {
    expect(breadcrumbItems('izin.laporan-pengajuan'))->toBe([
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Izin', 'url' => route('izin')],
        ['label' => 'Laporan Pengajuan', 'url' => null],
    ]);
});

test('a nested page builds the full trail', function () {
    expect(breadcrumbItems('knowledge.articles-create'))->toBe([
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Knowledge', 'url' => route('knowledge')],
        ['label' => 'Artikel', 'url' => route('knowledge.articles')],
        ['label' => 'Buat Artikel', 'url' => null],
    ]);
});

test('settings pages link the settings crumb to the profile page', function () {
    expect(breadcrumbItems('appearance.edit'))->toBe([
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pengaturan', 'url' => route('profile.edit')],
        ['label' => 'Tampilan', 'url' => null],
    ]);
});

test('an unmapped route falls back to a headline of its name', function () {
    expect(breadcrumbItems('some.unknown-page'))->toBe([
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Unknown Page', 'url' => null],
    ]);
});

test('without a route name it falls back to dashboard', function () {
    expect(breadcrumbItems(null))->toBe([
        ['label' => 'Dashboard', 'url' => null],
    ]);
});

test('it renders crumb labels', function () {
    Volt::test('components.breadcrumb', ['routeName' => 'izin.show'])
        ->assertSee('Dashboard')
        ->assertSee('Izin')
        ->assertSee('Detail Izin');
});
