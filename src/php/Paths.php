<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator;

final class Paths
{
    public static function root(): string
    {
        return dirname(__DIR__);
    }
}
