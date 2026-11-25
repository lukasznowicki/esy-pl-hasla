<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\I18n;

use Esy\PasswordGenerator\Paths;

final class Translator
{
    /**
     * @param array<string, string> $messages
     */
    public function __construct(
        public readonly string $locale,
        private readonly array $messages,
    ) {
    }

    public static function load(string $locale): self
    {
        if (!Locale::isSupported($locale)) {
            $locale = 'en';
        }

        $file = Paths::root() . '/lang/' . $locale . '.php';
        $messages = require $file;
        if (!is_array($messages)) {
            throw new \RuntimeException('Missing translations for ' . $locale);
        }

        $clean = [];
        foreach ($messages as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $clean[$key] = $value;
            }
        }

        return new self($locale, $clean);
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    public function t(string $key, array $replace = []): string
    {
        $text = $this->messages[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->messages;
    }
}
