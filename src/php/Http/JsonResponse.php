<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

use Esy\PasswordGenerator\I18n\Translator;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function send(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function fail(string $code, Translator $translator): never
    {
        self::send(self::status($code), [
            'ok' => false,
            'error' => $code,
            'message' => $translator->t('error.' . $code),
        ]);
    }

    public static function status(string $code): int
    {
        return match ($code) {
            'csrf', 'origin', 'consent_required' => 403,
            'rate_limited' => 429,
            'pattern_exhausted' => 422,
            'method' => 405,
            'body' => 413,
            default => 400,
        };
    }
}
