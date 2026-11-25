<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

use Esy\PasswordGenerator\DomainError;

enum Preset: string
{
    case Custom = 'custom';
    case Strong = 'strong';
    case VeryStrong = 'very-strong';
    case Quantum = 'quantum';

    public function requiresConsent(): bool
    {
        return $this === self::VeryStrong || $this === self::Quantum;
    }

    public static function fromString(string $value): self
    {
        $preset = self::tryFrom($value);
        if ($preset === null) {
            throw new DomainError('invalid_options');
        }

        return $preset;
    }

    public function assertAllowed(?string $consent): void
    {
        if ($this->requiresConsent() && $consent !== 'accept') {
            throw new DomainError('consent_required');
        }
    }
}
