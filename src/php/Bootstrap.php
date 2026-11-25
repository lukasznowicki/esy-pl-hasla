<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator;

final class Bootstrap
{
    public static function boot(): void
    {
        ini_set('default_charset', 'UTF-8');
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        session_name('ESYPGSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/password-generator/',
            'secure' => self::https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $token = $_SESSION['csrf'] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }

    public static function https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function csrf(): string
    {
        $token = $_SESSION['csrf'] ?? '';

        return is_string($token) ? $token : '';
    }
}
