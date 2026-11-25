<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

use Esy\PasswordGenerator\DomainError;
use Esy\PasswordGenerator\Paths;

final class QuantumAlphabet
{
    /** @var list<string>|null */
    private static ?array $characters = null;

    /**
     * @return list<string>
     */
    public static function characters(): array
    {
        if (self::$characters !== null) {
            return self::$characters;
        }

        $loaded = require Paths::root() . '/data/quantum-alphabet.php';
        if (!is_array($loaded)) {
            throw new \RuntimeException('Quantum alphabet is missing.');
        }

        $characters = [];
        foreach ($loaded as $character) {
            if (!is_string($character) || $character === '') {
                throw new \RuntimeException('Quantum alphabet is invalid.');
            }
            $characters[] = $character;
        }
        if (count($characters) !== 2048) {
            throw new \RuntimeException('Quantum alphabet must contain 2048 characters.');
        }

        self::$characters = $characters;

        return self::$characters;
    }
}
