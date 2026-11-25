<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

final class Verdict
{
    public static function fromBits(float $bits): string
    {
        return Entropy::verdict($bits);
    }

    public static function capped(float $bits, bool $hasFinding): string
    {
        $verdict = Entropy::verdict($bits);
        if ($hasFinding) {
            return Entropy::capAtWeak($verdict);
        }

        return $verdict;
    }
}
