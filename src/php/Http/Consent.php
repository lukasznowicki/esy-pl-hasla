<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

final class Consent
{
    public static function read(): ?string
    {
        $value = $_COOKIE[Cookies::CONSENT] ?? null;
        if ($value === 'accept' || $value === 'decline') {
            return $value;
        }

        return null;
    }

    public static function write(string $value, bool $https): void
    {
        Cookies::set(Cookies::CONSENT, $value, time() + 180 * 24 * 60 * 60, $https);
    }
}
