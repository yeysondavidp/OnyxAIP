<?php

use App\Livewire\Notifications\NotificationBell;
use App\Models\User;
use App\Notifications\PmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function seedNotification(User $user, array $overrides = []): DatabaseNotification
{
    return $user->notifications()->create(array_merge([
        'id'   => (string) Str::uuid(),
        'type' => PmNotification::class,
        'data' => [
            'slot'    => 'pm_job_status_changed',
            'label'   => 'PM — job status changed',
            'subject' => 'Job JOB-0001 is now Completed',
            'url'     => 'https://example.test/jobs/1',
        ],
    ], $overrides));
}

it('renders the unread count', function () {
    $pm = User::factory()->pm()->create();
    seedNotification($pm);
    seedNotification($pm);

    Livewire::actingAs($pm)
        ->test(NotificationBell::class)
        ->assertSee('2');
});

it('shows the empty state when there are no notifications', function () {
    $pm = User::factory()->pm()->create();

    Livewire::actingAs($pm)
        ->test(NotificationBell::class)
        ->set('open', true)
        ->assertSee("You're all caught up");
});

it('mark all as read clears the unread count', function () {
    $pm = User::factory()->pm()->create();
    seedNotification($pm);
    seedNotification($pm);

    Livewire::actingAs($pm)
        ->test(NotificationBell::class)
        ->call('markAllAsRead');

    expect($pm->fresh()->unreadNotifications()->count())->toBe(0);
});

it('opening a notification marks it read and redirects to its url', function () {
    $pm           = User::factory()->pm()->create();
    $notification = seedNotification($pm);

    Livewire::actingAs($pm)
        ->test(NotificationBell::class)
        ->call('openNotification', $notification->id, 'https://example.test/jobs/1')
        ->assertRedirect('https://example.test/jobs/1');

    expect($notification->fresh()->read_at)->not->toBeNull();
});
