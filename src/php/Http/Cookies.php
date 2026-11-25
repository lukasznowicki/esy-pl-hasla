<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

final class Cookies
{
    public const CONSENT = 'pg_consent';
    public const SETTINGS = 'pg_settings';
    public const PATH = '/password-generator/';

    public static function set(string $name, string $value, int $expires, bool $https): void
    {
        setcookie($name, $value, self::options($expires, $https));
        $_COOKIE[$name] = $value;
    }

    public static function clear(string $name, bool $https): void
    {
        setcookie($name, '', self::options(time() - 3600, $https));
        unset($_COOKIE[$name]);
    }

    /**
     * @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string}
     */
    private static function options(int $expires, bool $https): array
    {
        return [
            'expires' => $expires,
            'path' => self::PATH,
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
