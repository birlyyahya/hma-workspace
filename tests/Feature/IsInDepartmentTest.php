<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;

test('a user is in the department their role belongs to', function () {
    $it = Department::create(['code' => 'it', 'name' => 'IT', 'is_active' => true]);
    $role = Role::factory()->create(['department_id' => $it->id]);
    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->isInDepartment('it'))->toBeTrue()
        ->and($user->isInDepartment('hrd'))->toBeFalse();
});

test('a user counts as being in the parent department of their role department', function () {
    $it = Department::create(['code' => 'it', 'name' => 'IT', 'is_active' => true]);
    $software = Department::create(['code' => 'it-software', 'name' => 'IT Software', 'parent_id' => $it->id, 'is_active' => true]);
    $role = Role::factory()->create(['department_id' => $software->id]);
    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->isInDepartment('it-software'))->toBeTrue()
        ->and($user->isInDepartment('it'))->toBeTrue()
        ->and($user->isInDepartment('finance'))->toBeFalse();
});

test('a user whose role has no department is not in any department', function () {
    $role = Role::factory()->create(['department_id' => null]);
    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->isInDepartment('it'))->toBeFalse();
});
