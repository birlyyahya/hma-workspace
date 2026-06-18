<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function teamTab(User $actor, int $leaderId)
{
    return Volt::actingAs($actor)->test('project.components.project-team-tabs', [
        'id' => 1,
        'leaderId' => $leaderId,
        'internal' => [],
        'timduk' => [],
    ]);
}

test('project leader can invite an internal member', function () {
    $leader = User::factory()->create();
    $member = User::factory()->create();

    Http::fake([
        '*' => Http::response(['status' => 201, 'data' => ['user_id' => $member->id]], 200),
    ]);

    teamTab($leader, $leader->id)->call('inviteInternal', $member->id);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'project-teams')
        && $request->method() === 'POST'
        && (int) $request['user_id'] === $member->id);
});

test('a non-leader internal member cannot invite other members', function () {
    $leader = User::factory()->create();
    $intruder = User::factory()->create();
    $member = User::factory()->create();

    Http::fake();

    teamTab($intruder, $leader->id)->call('inviteInternal', $member->id);

    Http::assertNothingSent();
});

test('a super-admin can invite members even when not the leader', function () {
    $leader = User::factory()->create();
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $member = User::factory()->create();

    Http::fake([
        '*' => Http::response(['status' => 201, 'data' => ['user_id' => $member->id]], 200),
    ]);

    teamTab($admin, $leader->id)->call('inviteInternal', $member->id);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'project-teams')
        && $request->method() === 'POST');
});

test('project leader can add a support team (PPK)', function () {
    $leader = User::factory()->create();

    Http::fake([
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    teamTab($leader, $leader->id)
        ->set('nameTimduk', 'PPK Kejaksaan')
        ->call('addTimduk');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'projects/1')
        && $request->method() === 'PATCH');
});

test('a non-leader internal member cannot add a support team (PPK)', function () {
    $leader = User::factory()->create();
    $intruder = User::factory()->create();

    Http::fake();

    teamTab($intruder, $leader->id)
        ->set('nameTimduk', 'PPK Kejaksaan')
        ->call('addTimduk');

    Http::assertNothingSent();
});
