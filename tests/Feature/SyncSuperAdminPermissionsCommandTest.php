<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

function makePermissions(int $count): Collection
{
    return collect(range(1, $count))->map(fn ($i) => Permission::create([
        'name' => "module-baru.action{$i}",
        'module' => 'module-baru',
        'action' => "action{$i}",
        'label' => "Action {$i}",
    ]));
}

test('it grants every permission to the super-admin role', function () {
    $superAdmin = Role::factory()->superAdmin()->create();
    $other = Role::factory()->create();

    $permissions = makePermissions(4);

    // Super-admin starts with only one of them granted.
    $superAdmin->permissions()->attach($permissions->first()->id);

    $this->artisan('permissions:sync-super-admin')
        ->assertSuccessful();

    expect($superAdmin->fresh()->permissions()->pluck('permissions.id')->sort()->values()->all())
        ->toBe($permissions->pluck('id')->sort()->values()->all());

    // Other roles are untouched.
    expect($other->fresh()->permissions()->count())->toBe(0);
});

test('it fails gracefully when the super-admin role is missing', function () {
    makePermissions(2);

    $this->artisan('permissions:sync-super-admin')
        ->assertFailed();
});
