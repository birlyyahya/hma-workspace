<?php

use App\Models\Role;
use App\Models\User;

test('super-admin passes any permission check without a pivot row', function () {
    $superAdmin = User::factory()->create([
        'role_id' => Role::factory()->superAdmin()->create()->id,
    ]);

    // A brand-new module/permission that nobody has been granted yet.
    expect($superAdmin->hasPermission('module-baru.view'))->toBeTrue()
        ->and($superAdmin->hasPermission('apa.saja.yang.belum.ada'))->toBeTrue()
        ->and($superAdmin->hasAnyPermission(['module-baru.create', 'module-baru.delete']))->toBeTrue();
});

test('a non super-admin role still requires the permission to be granted', function () {
    $user = User::factory()->create([
        'role_id' => Role::factory()->create()->id,
    ]);

    expect($user->hasPermission('module-baru.view'))->toBeFalse()
        ->and($user->hasAnyPermission(['module-baru.create', 'module-baru.delete']))->toBeFalse();
});
