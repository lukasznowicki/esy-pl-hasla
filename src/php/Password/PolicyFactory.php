<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Password;

use Esy\PasswordGenerator\DomainError;

final class PolicyFactory
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 128;
    public const MIN_COUNT = 1;
    public const MAX_COUNT = 25;

    public static function fromPreset(Preset $preset, int $count = 1): Policy
    {
        $count = self::bound($count, self::MIN_COUNT, self::MAX_COUNT);

        return match ($preset) {
            Preset::Strong => new Policy($preset, Alphabet::fullAscii(), '', 16, $count, false),
            Preset::VeryStrong => new Policy($preset, Alphabet::fullAscii(), '', 20, $count, false),
            Preset::Quantum => new Policy($preset, Alphabet::fullAscii(), Alphabet::edges(true), 41, $count, true),
            Preset::Custom => throw new DomainError('invalid_options'),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function countFrom(array $options): int
    {
        if (!array_key_exists('count', $options)) {
            return 1;
        }
        if (!is_int($options['count'])) {
            throw new DomainError('invalid_options');
        }

        return self::bound($options['count'], self::MIN_COUNT, self::MAX_COUNT);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{
     *     lower: bool,
     *     upper: bool,
     *     digits: bool,
     *     specialBasic: bool,
     *     specialExtended: bool,
     *     similar: bool,
     *     edges: bool,
     *     length: int,
     *     count: int
     * }
     */
    public static function clampedOptions(array $options): array
    {
        return [
            'lower' => self::bool($options, 'lower'),
            'upper' => self::bool($options, 'upper'),
            'digits' => self::bool($options, 'digits'),
            'specialBasic' => self::bool($options, 'specialBasic'),
            'specialExtended' => self::bool($options, 'specialExtended'),
            'similar' => self::bool($options, 'similar'),
            'edges' => self::bool($options, 'edges'),
            'length' => self::bound(self::int($options, 'length'), self::MIN_LENGTH, self::MAX_LENGTH),
            'count' => self::bound(self::int($options, 'count'), self::MIN_COUNT, self::MAX_COUNT),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromCustom(array $options): Policy
    {
        $options = self::clampedOptions($options);
        $similar = $options['similar'];
        $edges = $options['edges'];
        $middle = Alphabet::middle(
            $options['lower'],
            $options['upper'],
            $options['digits'],
            $options['specialBasic'],
            $options['specialExtended'],
            $similar,
        );
        if ($middle === '') {
            throw new DomainError('empty_alphabet');
        }

        $edge = '';
        if ($edges) {
            $edge = Alphabet::edges($similar);
            if ($edge === '') {
                throw new DomainError('empty_alphabet');
            }
        }

        return new Policy(
            Preset::Custom,
            $middle,
            $edge,
            $options['length'],
            $options['count'],
            $edges,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function bool(array $options, string $key): bool
    {
        if (!array_key_exists($key, $options) || !is_bool($options[$key])) {
            throw new DomainError('invalid_options');
        }

        return $options[$key];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function int(array $options, string $key): int
    {
        if (!array_key_exists($key, $options) || !is_int($options[$key])) {
            throw new DomainError('invalid_options');
        }

        return $options[$key];
    }

    private static function bound(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
