<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->url = route('activitylog-ui.dashboard');
});

test('a user without permission cannot access the activity log UI', function () {
    $user = User::factory()->create(['role_id' => Role::factory()->create(['level' => 10])->id]);

    $this->actingAs($user)->get($this->url)->assertForbidden();
});

test('a super-admin can access the activity log UI', function () {
    $user = User::factory()->create(['role_id' => Role::factory()->superAdmin()->create()->id]);

    $this->actingAs($user)->get($this->url)->assertSuccessful();
});

test('a role granted activitylog.view can access the activity log UI', function () {
    $role = Role::factory()->create(['level' => 10]);
    $permission = Permission::firstOrCreate(
        ['name' => 'activitylog.view'],
        ['module' => 'activitylog', 'action' => 'view', 'label' => 'View Activity Log'],
    );
    $role->permissions()->attach($permission);

    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)->get($this->url)->assertSuccessful();
});
