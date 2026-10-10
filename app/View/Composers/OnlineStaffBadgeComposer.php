<?php

namespace App\View\Composers;

use App\Models\User;
use App\Services\Admin\UserPresenceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Supplies the shadow account's "N staff online" header badge. Other roles get null,
 * which hides the badge and skips the query.
 */
class OnlineStaffBadgeComposer
{
    public function __construct(
        private UserPresenceService $presence,
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        $view->with('headerOnlineStaffCount', $user instanceof User && $user->isShadow()
            ? $this->presence->onlineStaffCount()
            : null);
    }
}
