<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

use Esy\PasswordGenerator\DomainError;

final class RateLimiter
{
    private const LIMIT = 60;
    private const WINDOW = 900;

    public function hit(string $bucket): void
    {
        $now = time();
        $stamps = $_SESSION['rate'][$bucket] ?? [];
        if (!is_array($stamps)) {
            $stamps = [];
        }

        $fresh = [];
        foreach ($stamps as $stamp) {
            if (is_int($stamp) && $stamp > $now - self::WINDOW) {
                $fresh[] = $stamp;
            }
        }
        if (count($fresh) >= self::LIMIT) {
            $_SESSION['rate'][$bucket] = $fresh;
            throw new DomainError('rate_limited');
        }

        $fresh[] = $now;
        $_SESSION['rate'][$bucket] = $fresh;
    }
}
