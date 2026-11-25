<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

use Esy\PasswordGenerator\DomainError;

final class RequestGuard
{
    public static function assertPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new DomainError('method');
        }
    }

    public static function assertJsonContent(): void
    {
        $header = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $media = strtolower(trim(explode(';', $header, 2)[0]));
        if ($media !== 'application/json') {
            throw new DomainError('content_type');
        }
    }

    public static function body(): string
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '' || strlen($raw) > 8192) {
            throw new DomainError('body');
        }

        return $raw;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $raw): array
    {
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new DomainError('body');
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new DomainError('body');
        }

        return $data;
    }

    public static function assertOrigin(): void
    {
        $expected = self::expectedOrigin();
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $fetchSite = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($origin !== '') {
            if (!hash_equals($expected, $origin)) {
                throw new DomainError('origin');
            }
        } elseif ($fetchSite !== '') {
            if ($fetchSite !== 'same-origin') {
                throw new DomainError('origin');
            }
        } else {
            $prefix = $expected . '/password-generator';
            if ($referer === '' || !str_starts_with($referer, $prefix)) {
                throw new DomainError('origin');
            }
        }

        if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
            throw new DomainError('origin');
        }
    }

    public static function assertCsrf(): void
    {
        $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $known = (string) ($_SESSION['csrf'] ?? '');
        if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
            throw new DomainError('csrf');
        }
    }

    private static function expectedOrigin(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $bare = strtolower($host);
        if (preg_match('/^(\[[^\]]+\]|[^:]+):(\d+)$/', $bare, $matches) === 1) {
            $bare = $matches[1];
        }

        if ($bare === 'esy.pl') {
            return 'https://esy.pl';
        }
        if ($bare === 'localhost' || $bare === '127.0.0.1') {
            $scheme = \Esy\PasswordGenerator\Bootstrap::https() ? 'https' : 'http';

            return $scheme . '://' . $host;
        }

        throw new DomainError('origin');
    }
}
