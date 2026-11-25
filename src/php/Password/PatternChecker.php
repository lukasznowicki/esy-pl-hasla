<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

use Esy\PasswordGenerator\Paths;

final class PatternChecker
{
    /** @var array<string, true>|null */
    private static ?array $dictionary = null;

    /** @var list<string>|null */
    private static ?array $keyboardFragments = null;

    private const ROWS = [
        'qwertyuiop',
        'asdfghjkl',
        'zxcvbnm',
        '1234567890',
        '!@#$%^&*()',
        'azertyuiop',
        'qsdfghjklm',
        'wxcvbn',
    ];

    /**
     * @return list<array{code: string, match: string}>
     */
    public function findings(string $password): array
    {
        $found = [];
        $lower = self::lowerAscii($password);
        $leet = strtr($lower, [
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 't',
            '@' => 'a',
            '$' => 's',
        ]);

        $dictionary = $this->dictionaryMatch($password, $lower);
        if ($dictionary === null) {
            $dictionary = $this->dictionaryMatch($password, $leet);
        }
        if ($dictionary !== null) {
            $found[] = ['code' => 'dictionary', 'match' => $dictionary];
        }

        $keyboard = $this->keyboardMatch($password, $lower);
        if ($keyboard !== null) {
            $found[] = ['code' => 'keyboard', 'match' => $keyboard];
        }

        $sequence = $this->sequenceMatch($password);
        if ($sequence !== null) {
            $found[] = ['code' => 'sequence', 'match' => $sequence];
        }

        $repeat = $this->repeatMatch($password);
        if ($repeat !== null) {
            $found[] = ['code' => 'repeat', 'match' => $repeat];
        }

        return $found;
    }

    /**
     * @return list<string>|null
     */
    public static function codepoints(string $value): ?array
    {
        if ($value !== '' && preg_match('//u', $value) !== 1) {
            return null;
        }
        if ($value === '') {
            return [];
        }

        $parts = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return null;
        }

        return $parts;
    }

    public static function lowerAscii(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private function dictionaryMatch(string $original, string $folded): ?string
    {
        foreach (self::dictionary() as $word => $present) {
            $word = (string) $word;
            if ($present !== true || strlen($word) < 4) {
                continue;
            }
            $position = strpos($folded, $word);
            if ($position === false) {
                continue;
            }

            return substr($original, $position, strlen($word));
        }

        return null;
    }

    private function keyboardMatch(string $original, string $lower): ?string
    {
        $special = strpos($original, '!@#');
        if ($special !== false) {
            return '!@#';
        }

        foreach (self::keyboardFragments() as $fragment) {
            $position = strpos($lower, (string) $fragment);
            if ($position === false) {
                continue;
            }

            return substr($original, $position, strlen($fragment));
        }

        return null;
    }

    private function sequenceMatch(string $password): ?string
    {
        $chars = self::codepoints($password);
        if ($chars === null) {
            return null;
        }

        $total = count($chars);
        for ($index = 0; $index <= $total - 4; $index++) {
            $kind = self::asciiKind($chars[$index]);
            if ($kind === null || strlen($chars[$index + 1]) !== 1) {
                continue;
            }
            if (self::asciiKind($chars[$index + 1]) !== $kind) {
                continue;
            }
            $step = ord($chars[$index + 1]) - ord($chars[$index]);
            if ($step !== 1 && $step !== -1) {
                continue;
            }

            $matches = true;
            for ($offset = 2; $offset < 4; $offset++) {
                $current = $chars[$index + $offset];
                $previous = $chars[$index + $offset - 1];
                if (strlen($current) !== 1 || self::asciiKind($current) !== $kind) {
                    $matches = false;
                    break;
                }
                if (ord($current) - ord($previous) !== $step) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $chars[$index] . $chars[$index + 1] . $chars[$index + 2] . $chars[$index + 3];
            }
        }

        return null;
    }

    private function repeatMatch(string $password): ?string
    {
        $chars = self::codepoints($password);
        if ($chars === null) {
            return null;
        }

        $total = count($chars);
        for ($index = 0; $index <= $total - 4; $index++) {
            $character = $chars[$index];
            if ($character === $chars[$index + 1] && $character === $chars[$index + 2] && $character === $chars[$index + 3]) {
                return $character . $character . $character . $character;
            }
        }

        return null;
    }

    private static function asciiKind(string $character): ?string
    {
        if (strlen($character) !== 1) {
            return null;
        }
        if (($character >= 'a' && $character <= 'z') || ($character >= 'A' && $character <= 'Z')) {
            return 'letter';
        }
        if ($character >= '0' && $character <= '9') {
            return 'digit';
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    private static function dictionary(): array
    {
        if (self::$dictionary !== null) {
            return self::$dictionary;
        }

        $loaded = require Paths::root() . '/data/common-passwords.php';
        if (!is_array($loaded)) {
            throw new \RuntimeException('Password list is missing.');
        }

        $dictionary = [];
        $words = array_is_list($loaded) ? $loaded : array_keys($loaded);
        foreach ($words as $word) {
            if (is_int($word)) {
                $word = (string) $word;
            }
            if (!is_string($word) || strlen($word) < 4) {
                continue;
            }
            $dictionary[$word] = true;
        }
        self::$dictionary = $dictionary;

        return self::$dictionary;
    }

    /**
     * @return list<string>
     */
    private static function keyboardFragments(): array
    {
        if (self::$keyboardFragments !== null) {
            return self::$keyboardFragments;
        }

        $unique = [];
        foreach (self::ROWS as $row) {
            foreach ([$row, strrev($row)] as $direction) {
                $length = strlen($direction);
                for ($size = 4; $size <= $length; $size++) {
                    for ($offset = 0; $offset <= $length - $size; $offset++) {
                        $fragment = substr($direction, $offset, $size);
                        $unique[$fragment] = $fragment;
                    }
                }
            }
        }

        self::$keyboardFragments = array_values($unique);

        return self::$keyboardFragments;
    }
}
