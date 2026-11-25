<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

use Esy\PasswordGenerator\DomainError;
use Random\Engine\Secure;
use Random\Randomizer;

final class Drawer
{
    public function __construct(private readonly Randomizer $randomizer)
    {
    }

    public static function secure(): self
    {
        return new self(new Randomizer(new Secure()));
    }

    public function ascii(string $alphabet, int $length): string
    {
        if ($alphabet === '' || $length < 1) {
            throw new DomainError('empty_alphabet');
        }

        return $this->randomizer->getBytesFromString($alphabet, $length);
    }

    /**
     * @param list<string> $characters
     */
    public function chars(array $characters, int $length): string
    {
        $max = count($characters) - 1;
        if ($max < 0 || $length < 1) {
            throw new DomainError('empty_alphabet');
        }

        $password = '';
        for ($index = 0; $index < $length; $index++) {
            $password .= $characters[$this->randomizer->getInt(0, $max)];
        }

        return $password;
    }
}
