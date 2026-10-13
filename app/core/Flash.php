<?php
namespace JanKavach\Core;

/**
 * One-shot session messages rendered by the layout as Bootstrap alerts.
 * Also carries validation errors and old input across a redirect so forms
 * can be repopulated after a failed POST.
 */
class Flash
{
    private const KEY = '_flash';

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('danger', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function add(string $type, string $message): void
    {
        $messages = Session::get(self::KEY, []);
        $messages[] = ['type' => $type, 'message' => $message];
        Session::set(self::KEY, $messages);
    }

    /** Read and clear every queued message. */
    public static function pull(): array
    {
        return Session::pull(self::KEY, []);
    }

    public static function has(): bool
    {
        return !empty(Session::get(self::KEY, []));
    }

    // ------------------------------------------------------------------
    // Validation errors and old input
    // ------------------------------------------------------------------

    public static function setErrors(array $errors): void
    {
        Session::set('_errors', $errors);
    }

    public static function errors(): array
    {
        return Session::pull('_errors', []);
    }

    public static function setOldInput(array $input): void
    {
        // Never bounce a password back into the form.
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['new_password']);
        Session::set('_old_input', $input);
    }

    public static function oldInput(): array
    {
        return Session::pull('_old_input', []);
    }
}
