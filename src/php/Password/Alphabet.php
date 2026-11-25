<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

final class Alphabet
{
    public const LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const DIGITS = '0123456789';
    public const SPECIAL_BASIC = '_-.';
    public const SPECIAL_EXTENDED = '!?@#$%^&*(){}[]:;,<>';
    public const SIMILAR = '0Oo1lIi';
    public const LETTERS = self::LOWER . self::UPPER;

    public static function stripSimilar(string $alphabet): string
    {
        return str_replace(str_split(self::SIMILAR), '', $alphabet);
    }

    public static function fullAscii(): string
    {
        return self::LOWER . self::UPPER . self::DIGITS . self::SPECIAL_BASIC . self::SPECIAL_EXTENDED;
    }

    public static function middle(
        bool $lower,
        bool $upper,
        bool $digits,
        bool $basic,
        bool $extended,
        bool $similar,
    ): string {
        $alphabet = '';
        if ($lower) {
            $alphabet .= self::LOWER;
        }
        if ($upper) {
            $alphabet .= self::UPPER;
        }
        if ($digits) {
            $alphabet .= self::DIGITS;
        }
        if ($basic) {
            $alphabet .= self::SPECIAL_BASIC;
        }
        if ($extended) {
            $alphabet .= self::SPECIAL_EXTENDED;
        }
        if ($similar) {
            $alphabet = self::stripSimilar($alphabet);
        }

        return $alphabet;
    }

    public static function edges(bool $similar): string
    {
        $alphabet = self::LETTERS;
        if ($similar) {
            $alphabet = self::stripSimilar($alphabet);
        }

        return $alphabet;
    }
}
