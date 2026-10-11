<?php

namespace App\Support;

/**
 * Sign-in lockout, shared by the login flow and the shadow sign-in monitor so both agree on which
 * rate-limiter key belongs to a login name.
 *
 * The count is kept per login name AND per source address. Eight wrong passwords lock that login for 15
 * minutes for the address that typed them; a stranger guessing from elsewhere cannot lock the real person
 * out. (The per-address limit on the sign-in route still slows a guesser who moves between addresses.)
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 8;

    public const LOCKOUT_SECONDS = 900;

    /**
     * Failed sign-ins one network address may make in a minute, whichever logins it tries. Only failures count: an
     * office whose staff all sign in at 8 o'clock behind one address must not lock itself out. (The per-login lockout
     * above stops guessing at one account; this stops one address from trying many accounts.)
     */
    public const IP_MAX_FAILURES_PER_MINUTE = 15;

    public const IP_WINDOW_SECONDS = 60;

    public static function ipKey(?string $ipAddress): string
    {
        return 'auth:login-ip:'.sha1((string) $ipAddress);
    }

    public static function accountKey(string $loginInput, ?string $ipAddress = null): string
    {
        $normalizedLogin = mb_strtolower(trim($loginInput));

        return 'auth:login-account:'.sha1($ipAddress === null ? $normalizedLogin : $normalizedLogin.'|'.$ipAddress);
    }
}
