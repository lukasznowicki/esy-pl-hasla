<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\I18n;

final class Locale
{
    public const SUPPORTED = ['en', 'pl', 'fr', 'es', 'zh'];

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function htmlLang(string $locale): string
    {
        return $locale === 'zh' ? 'zh-CN' : $locale;
    }

    public static function resolve(?string $query, ?string $cookieLang, bool $consent, ?string $acceptLanguage): string
    {
        if (is_string($query) && self::isSupported($query)) {
            return $query;
        }
        if ($consent && is_string($cookieLang) && self::isSupported($cookieLang)) {
            return $cookieLang;
        }

        return self::fromAcceptLanguage($acceptLanguage) ?? 'en';
    }

    private static function fromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $ranked = [];
        foreach (explode(',', $header) as $index => $part) {
            $bits = explode(';', trim($part));
            $tag = strtolower(trim($bits[0] ?? ''));
            if ($tag === '' || $tag === '*') {
                continue;
            }
            $quality = 1.0;
            foreach ($bits as $bit) {
                $bit = trim($bit);
                if (str_starts_with($bit, 'q=')) {
                    $quality = (float) substr($bit, 2);
                }
            }
            $ranked[] = [$quality, $index, $tag];
        }

        usort($ranked, static function (array $left, array $right): int {
            if ($left[0] === $right[0]) {
                return $left[1] <=> $right[1];
            }

            return $right[0] <=> $left[0];
        });

        foreach ($ranked as $item) {
            $mapped = self::mapTag($item[2]);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }

    private static function mapTag(string $tag): ?string
    {
        $tag = str_replace('_', '-', $tag);
        if (
            str_starts_with($tag, 'zh-hant')
            || $tag === 'zh-tw'
            || $tag === 'zh-hk'
            || $tag === 'zh-mo'
        ) {
            return null;
        }
        if ($tag === 'zh' || str_starts_with($tag, 'zh-hans') || $tag === 'zh-cn' || $tag === 'zh-sg') {
            return 'zh';
        }

        $primary = explode('-', $tag)[0];
        if (in_array($primary, ['en', 'pl', 'fr', 'es'], true)) {
            return $primary;
        }

        return null;
    }
}
