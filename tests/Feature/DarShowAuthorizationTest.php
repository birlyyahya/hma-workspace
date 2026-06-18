<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function fakeDarTask(?int $ownerId = null, array $teamUserIds = []): void
{
    $task = $ownerId === null
        ? []
        : [
            'id' => 1,
            'activity' => 'Secret Task',
            'user_id' => $ownerId,
            'team_user' => collect($teamUserIds)
                ->map(fn ($id) => ['user_id' => $id])
                ->all(),
            'comments' => [],
        ];

    Http::fake([
        '*log-activity*' => Http::response(['data' => []]),
        '*activity?id=*' => Http::response(['data' => $task]),
        '*' => Http::response(['status' => 200, 'success' => true, 'data' => []]),
    ]);
}

test('the task owner can open the dar detail', function () {
    $owner = User::factory()->create();
    fakeDarTask(ownerId: $owner->id);

    $this->actingAs($owner)
        ->get(route('dar.dar-show', ['id' => 1]))
        ->assertOk()
        ->assertSee('Secret Task');
});

test('a team member can open the dar detail', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    fakeDarTask(ownerId: $owner->id, teamUserIds: [$member->id]);

    $this->actingAs($member)
        ->get(route('dar.dar-show', ['id' => 1]))
        ->assertOk()
        ->assertSee('Secret Task');
});

test('a user with dar view-all scope can open any dar detail', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    fakeDarTask(ownerId: $owner->id);

    $this->actingAs($admin)
        ->get(route('dar.dar-show', ['id' => 1]))
        ->assertOk()
        ->assertSee('Secret Task');
});

test('an unrelated user is forbidden from opening the dar detail', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    fakeDarTask(ownerId: $owner->id);

    $this->actingAs($intruder)
        ->get(route('dar.dar-show', ['id' => 1]))
        ->assertForbidden()
        ->assertDontSee('Secret Task');
});

test('a missing dar task returns a 404 not found', function () {
    $user = User::factory()->create();
    fakeDarTask(ownerId: null);

    $this->actingAs($user)
        ->get(route('dar.dar-show', ['id' => 999]))
        ->assertNotFound();
});
