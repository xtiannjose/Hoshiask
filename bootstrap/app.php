<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\DropMalformedArrayInput;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureLegalConsentAccepted;
use App\Http\Middleware\EnsurePasswordChange;
use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\ThrottlePerLimit;
use App\Http\Middleware\TrackLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SetSecurityHeaders::class);
        $middleware->append(DropMalformedArrayInput::class);
        $middleware->web(append: [TrackLastSeen::class]);
        $middleware->trustHosts();
        // Trusted proxies are applied in AppServiceProvider::boot(), after config (and .env) has loaded.

        // A visitor who was signed in but whose session has ended (the "signed in" hint
        // cookie is still there) is told why they are back at the sign-in page.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->cookie(AuthController::SIGNED_IN_HINT_COOKIE) !== null) {
                $request->session()->flash('login_notice', 'Your session expired. Please sign in again.');
            }

            return route('login');
        });

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureActiveUser::class,
            'consent.accepted' => EnsureLegalConsentAccepted::class,
            'system_logs.unlocked' => EnsureSystemLogsUnlocked::class,
            'password.change.required' => EnsurePasswordChange::class,
            'throttle' => ThrottlePerLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
