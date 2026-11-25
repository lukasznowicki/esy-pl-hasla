<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

use Esy\PasswordGenerator\DomainError;

final class Assessor
{
    public function __construct(private readonly PatternChecker $patterns)
    {
    }

    /**
     * @return array{
     *     bits: float,
     *     verdict: string,
     *     classes: array<string, bool>,
     *     findings: list<array{code: string, match: string}>
     * }
     */
    public function assess(string $password): array
    {
        $chars = PatternChecker::codepoints($password);
        if ($chars === null) {
            throw new DomainError('invalid_options');
        }
        if ($chars === []) {
            throw new DomainError('empty_password');
        }
        if (count($chars) > 256) {
            throw new DomainError('too_long');
        }

        $classes = [
            'lower' => false,
            'upper' => false,
            'digits' => false,
            'specialBasic' => false,
            'specialExtended' => false,
            'other' => false,
        ];
        $others = [];
        foreach ($chars as $character) {
            if (strlen($character) === 1 && str_contains(Alphabet::LOWER, $character)) {
                $classes['lower'] = true;
            } elseif (strlen($character) === 1 && str_contains(Alphabet::UPPER, $character)) {
                $classes['upper'] = true;
            } elseif (strlen($character) === 1 && str_contains(Alphabet::DIGITS, $character)) {
                $classes['digits'] = true;
            } elseif (strlen($character) === 1 && str_contains(Alphabet::SPECIAL_BASIC, $character)) {
                $classes['specialBasic'] = true;
            } elseif (strlen($character) === 1 && str_contains(Alphabet::SPECIAL_EXTENDED, $character)) {
                $classes['specialExtended'] = true;
            } else {
                $classes['other'] = true;
                $others[$character] = true;
            }
        }

        $pool = 0;
        if ($classes['lower']) {
            $pool += strlen(Alphabet::LOWER);
        }
        if ($classes['upper']) {
            $pool += strlen(Alphabet::UPPER);
        }
        if ($classes['digits']) {
            $pool += strlen(Alphabet::DIGITS);
        }
        if ($classes['specialBasic']) {
            $pool += strlen(Alphabet::SPECIAL_BASIC);
        }
        if ($classes['specialExtended']) {
            $pool += strlen(Alphabet::SPECIAL_EXTENDED);
        }
        if ($classes['other']) {
            $pool += count($others);
        }

        $bits = $pool > 0 ? count($chars) * Entropy::log2($pool) : 0.0;
        $findings = $this->patterns->findings($password);

        return [
            'bits' => $bits,
            'verdict' => Verdict::capped($bits, $findings !== []),
            'classes' => $classes,
            'findings' => $findings,
        ];
    }
}
