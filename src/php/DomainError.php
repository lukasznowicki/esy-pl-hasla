<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator;

final class DomainError extends \RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
