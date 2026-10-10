<?php

namespace App\View\Composers;

use App\Services\HeaderNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HeaderNotificationsComposer
{
    public function __construct(
        private HeaderNotificationService $notifications,
    ) {}

    public function compose(View $view): void
    {
        $payload = $this->notifications->payloadFor(Auth::user());

        $view->with('headerNotifications', collect($payload['notifications'] ?? []));
        $view->with('headerNotificationUnreadCount', (int) ($payload['unread_count'] ?? 0));
    }
}
