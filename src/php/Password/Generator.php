<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

use Esy\PasswordGenerator\DomainError;

final class Generator
{
    public const MAX_ATTEMPTS = 32;

    public function __construct(
        private readonly Drawer $drawer,
        private readonly PatternChecker $patterns,
    ) {
    }

    /**
     * @return list<string>
     */
    public function generate(Policy $policy): array
    {
        $passwords = [];
        for ($index = 0; $index < $policy->count; $index++) {
            $passwords[] = $this->one($policy);
        }

        return $passwords;
    }

    private function one(Policy $policy): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $password = $this->draw($policy);
            if ($this->patterns->findings($password) === []) {
                return $password;
            }
        }

        throw new DomainError('pattern_exhausted');
    }

    private function draw(Policy $policy): string
    {
        if ($policy->characters !== null) {
            return $this->drawer->chars($policy->characters, $policy->length);
        }
        if ($policy->edges) {
            return $this->drawer->ascii($policy->edgeAlphabet, 1)
                . $this->drawer->ascii($policy->middleAlphabet, $policy->length - 2)
                . $this->drawer->ascii($policy->edgeAlphabet, 1);
        }

        return $this->drawer->ascii($policy->middleAlphabet, $policy->length);
    }
}
