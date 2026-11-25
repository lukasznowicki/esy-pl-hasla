<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

use Esy\PasswordGenerator\DomainError;
use Esy\PasswordGenerator\I18n\Locale;
use Esy\PasswordGenerator\Password\PolicyFactory;
use Esy\PasswordGenerator\Password\Preset;

final class SettingsCookie
{
    /**
     * @return array{
     *     lang: string,
     *     preset: string,
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
    public static function defaults(): array
    {
        return [
            'lang' => 'en',
            'preset' => Preset::Quantum->value,
            'lower' => true,
            'upper' => true,
            'digits' => true,
            'specialBasic' => true,
            'specialExtended' => true,
            'similar' => false,
            'edges' => false,
            'length' => 16,
            'count' => 1,
        ];
    }

    /**
     * @return array<string, bool|int|string>|null
     */
    public static function read(): ?array
    {
        $raw = $_COOKIE[Cookies::SETTINGS] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode(rawurldecode($raw), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }

        try {
            return self::normalize($decoded);
        } catch (DomainError) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, bool|int|string>
     */
    public static function normalize(array $input): array
    {
        $lang = $input['lang'] ?? 'en';
        if (!is_string($lang) || !Locale::isSupported($lang)) {
            throw new DomainError('invalid_options');
        }

        $presetRaw = $input['preset'] ?? '';
        if (!is_string($presetRaw)) {
            throw new DomainError('invalid_options');
        }
        $preset = Preset::fromString($presetRaw);

        $options = PolicyFactory::clampedOptions(self::optionFields($input));

        return [
            'lang' => $lang,
            'preset' => $preset->value,
            'lower' => $options['lower'],
            'upper' => $options['upper'],
            'digits' => $options['digits'],
            'specialBasic' => $options['specialBasic'],
            'specialExtended' => $options['specialExtended'],
            'similar' => $options['similar'],
            'edges' => $options['edges'],
            'length' => $options['length'],
            'count' => $options['count'],
        ];
    }

    /**
     * @param array<string, bool|int|string> $normalized
     */
    public static function store(array $normalized, bool $https): void
    {
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        Cookies::set(Cookies::SETTINGS, rawurlencode($json), time() + 7 * 24 * 60 * 60, $https);
    }

    public static function clear(bool $https): void
    {
        Cookies::clear(Cookies::SETTINGS, $https);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function optionFields(array $input): array
    {
        if (isset($input['options']) && is_array($input['options'])) {
            return $input['options'];
        }

        return $input;
    }
}
