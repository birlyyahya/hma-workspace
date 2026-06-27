<?php

use App\Models\TaskAssignments;
use App\Models\Tasks;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('saveTask creates exactly one task with assignments and notifies assignees', function () {
    Notification::fake();

    $creator = User::factory()->create();
    $assignee = User::factory()->create();

    Volt::actingAs($creator)
        ->test('widget.dashboard.task')
        ->set('taskName', 'Quarterly report')
        ->set('taskDueDate', now()->addDay()->format('Y-m-d'))
        ->set('taskPriority', 'high')
        ->set('assign', [['id' => $assignee->id, 'name' => $assignee->name]])
        ->call('saveTask')
        ->assertHasNoErrors();

    expect(Tasks::where('name', 'Quarterly report')->count())->toBe(1)
        ->and(TaskAssignments::where('user_id', $assignee->id)->count())->toBe(1);

    Notification::assertSentTo($assignee, TaskAssignedNotification::class);
});

test('saveTask requires at least one assignee and creates nothing when missing', function () {
    $creator = User::factory()->create();

    Volt::actingAs($creator)
        ->test('widget.dashboard.task')
        ->set('taskName', 'Orphan task')
        ->set('taskDueDate', now()->addDay()->format('Y-m-d'))
        ->set('assign', [])
        ->call('saveTask')
        ->assertHasErrors('assign');

    expect(Tasks::where('name', 'Orphan task')->exists())->toBeFalse();
});
