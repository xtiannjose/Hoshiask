<?php

namespace App\Providers;

use App\View\Composers\HeaderNotificationsComposer;
use App\View\Composers\OnlineStaffBadgeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewComposerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', HeaderNotificationsComposer::class);
        View::composer('layouts.partials.app-header', OnlineStaffBadgeComposer::class);
    }
}
