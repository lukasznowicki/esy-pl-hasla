<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

final class Entropy
{
    public static function log2(int $size): float
    {
        if ($size <= 0) {
            return 0.0;
        }
        if (($size & ($size - 1)) === 0) {
            $exponent = 0;
            $rest = $size;
            while ($rest > 1) {
                $rest >>= 1;
                $exponent++;
            }

            return (float) $exponent;
        }

        return log($size) / log(2);
    }

    public static function bits(int $length, int $middleSize, bool $edges, int $edgeSize): float
    {
        if ($middleSize <= 0) {
            return 0.0;
        }
        if ($edges) {
            if ($edgeSize <= 0 || $length < 2) {
                return 0.0;
            }

            return (2 * self::log2($edgeSize)) + (($length - 2) * self::log2($middleSize));
        }

        return $length * self::log2($middleSize);
    }

    public static function bitsForPolicy(Policy $policy): float
    {
        $middleSize = $policy->characters !== null
            ? count($policy->characters)
            : strlen($policy->middleAlphabet);

        return self::bits($policy->length, $middleSize, $policy->edges, strlen($policy->edgeAlphabet));
    }

    public static function verdict(float $bits): string
    {
        if ($bits < 40) {
            return 'very_weak';
        }
        if ($bits < 64) {
            return 'weak';
        }
        if ($bits < 80) {
            return 'moderate';
        }
        if ($bits < 128) {
            return 'strong';
        }
        if ($bits < 256) {
            return 'very_strong';
        }

        return 'quantum';
    }

    public static function capAtWeak(string $verdict): string
    {
        $rank = [
            'very_weak' => 0,
            'weak' => 1,
            'moderate' => 2,
            'strong' => 3,
            'very_strong' => 4,
            'quantum' => 5,
        ];
        if (($rank[$verdict] ?? 0) > $rank['weak']) {
            return 'weak';
        }

        return $verdict;
    }

    public static function format(float $bits): string
    {
        $rounded = round($bits, 1);
        if (abs($rounded - round($rounded)) < 1e-6) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 1, '.', '');
    }
}
