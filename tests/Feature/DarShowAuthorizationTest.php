<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(fn () => Livewire::withoutLazyLoading());

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

test('an unrelated user sees the forbidden state instead of the dar detail', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    fakeDarTask(ownerId: $owner->id);

    Volt::actingAs($intruder)
        ->test('dar.dar-show', ['id' => 1])
        ->assertOk()
        ->assertSet('forbidden', true)
        ->assertSet('task', [])
        ->assertSee('Akses Ditolak')
        ->assertDontSee('Secret Task');
});

test('a missing dar task shows the not found state', function () {
    $user = User::factory()->create();
    fakeDarTask(ownerId: null);

    Volt::actingAs($user)
        ->test('dar.dar-show', ['id' => 999])
        ->assertOk()
        ->assertSet('notFound', true)
        ->assertSee('Tidak Ditemukan');
});

test('team picker users and project list load only when editing starts, not at mount', function () {
    Role::factory()->count(2)->create();
    $role = Role::factory()->create();
    $owner = User::factory()->create(['role_id' => $role->id]);
    fakeDarTask(ownerId: $owner->id);

    $component = Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->assertSet('availableUsers', [])
        ->assertSet('projectData', []);

    $component->call('startEditing')->assertSet('editing', true);

    expect($component->get('availableUsers'))->not->toBeEmpty();
});
