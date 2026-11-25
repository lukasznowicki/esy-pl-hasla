<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

readonly class Policy
{
    /**
     * @param list<string>|null $characters
     */
    public function __construct(
        public Preset $preset,
        public string $middleAlphabet,
        public string $edgeAlphabet,
        public int $length,
        public int $count,
        public bool $edges,
        public ?array $characters = null,
    ) {
    }
}
