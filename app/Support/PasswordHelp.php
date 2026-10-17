<?php

namespace App\Support;

/**
 * The "forgot your password?" guidance shown on the sign-in page and in the lockout message.
 * There is no self-service reset, so people are pointed to whoever can reset it for them. That
 * contact (an email address, a phone number or a name) is a setting so it can be filled in per install.
 */
final class PasswordHelp
{
    public static function contact(): ?string
    {
        $contact = trim((string) config('hoshiask.password_help_contact', ''));

        return $contact === '' ? null : $contact;
    }

    public static function contactIsEmail(): bool
    {
        $contact = self::contact();

        return $contact !== null && filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Plain-text sentence, used in messages. */
    public static function sentence(): string
    {
        $contact = self::contact();

        return $contact === null
            ? 'Forgot your password? Contact your administrator.'
            : 'Forgot your password? Contact your administrator at '.$contact.'.';
    }

    /** The same advice for a person locked out of the sign-in form. */
    public static function lockoutSentence(): string
    {
        $contact = self::contact();

        return $contact === null
            ? 'Need help sooner? Contact your administrator.'
            : 'Need help sooner? Contact your administrator at '.$contact.'.';
    }
}
