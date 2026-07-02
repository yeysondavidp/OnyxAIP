<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * PM in-app notification bell/drawer (US-13.1) — rendered once, globally, in
 * the shared PM layout header. Polls periodically rather than pushing over a
 * websocket connection — no broadcasting infrastructure in this stack (ADR-002
 * lean-server footprint).
 */
class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function openNotification(string $notificationId, string $url): void
    {
        $notification = auth()->user()->notifications()->find($notificationId);
        $notification?->markAsRead();

        $this->redirect($url !== '' ? $url : route('dashboard'));
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notifications.notification-bell', [
            'unreadCount'   => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
