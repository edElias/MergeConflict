<?php
// MER-26: Shared account helpers for the profile page and password reset.

const PASSWORD_MIN_LENGTH = 8;
const AVATAR_DIR = __DIR__ . '/../../data/avatars';
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

// Field errors for a new password, keyed by field name ('password' / 'confirm').
// Empty array when the password is acceptable.
function new_password_errors(string $password, string $confirm): array
{
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if ($confirm !== $password) {
        $errors['confirm'] = "Passwords don't match.";
    }
    return $errors;
}

// Formats a 10-digit US phone number as "(555) 123-4567"; null if it isn't 10 digits.
// A leading country code of 1 is allowed.
function format_phone(string $input): ?string
{
    $digits = preg_replace('/\D/', '', $input);
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) {
        return null;
    }
    return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
}

// Keeps $_SESSION['avatar_version'] in sync with the user's stored photo. The
// version (the photo's file name, which is new on every upload) is added to avatar
// URLs so a replaced photo is never served from the browser cache.
function sync_avatar_session(?string $avatarFile): void
{
    $path = $avatarFile ? AVATAR_DIR . '/' . basename($avatarFile) : null;
    if ($path && is_file($path)) {
        $_SESSION['avatar_version'] = pathinfo($path, PATHINFO_FILENAME);
    } else {
        unset($_SESSION['avatar_version']);
    }
}

// Per-session token that every state-changing form posts back.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
