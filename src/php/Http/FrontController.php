<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

use Esy\PasswordGenerator\Bootstrap;
use Esy\PasswordGenerator\DomainError;
use Esy\PasswordGenerator\I18n\Locale;
use Esy\PasswordGenerator\I18n\Translator;
use Esy\PasswordGenerator\Password\Assessor;
use Esy\PasswordGenerator\Password\Drawer;
use Esy\PasswordGenerator\Password\Entropy;
use Esy\PasswordGenerator\Password\Generator;
use Esy\PasswordGenerator\Password\PatternChecker;
use Esy\PasswordGenerator\Password\PolicyFactory;
use Esy\PasswordGenerator\Password\Preset;

final class FrontController
{
    public static function handle(): void
    {
        $translator = null;
        try {
            RequestGuard::assertPost();
            RequestGuard::assertJsonContent();
            $raw = RequestGuard::body();
            RequestGuard::assertOrigin();
            RequestGuard::assertCsrf();
            $data = RequestGuard::decode($raw);
            $translator = self::translator($data);
            $action = $data['action'] ?? '';
            if (!is_string($action)) {
                throw new DomainError('invalid_options');
            }

            match ($action) {
                'consent' => self::consent($data, $translator),
                'settings' => self::settings($data, $translator),
                'generate' => self::generate($data, $translator),
                'check' => self::check($data, $translator),
                default => throw new DomainError('invalid_options'),
            };
        } catch (DomainError $error) {
            $translator ??= Translator::load('en');
            JsonResponse::fail($error->errorCode, $translator);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function translator(array $data): Translator
    {
        $requested = $data['lang'] ?? null;
        $locale = is_string($requested) && Locale::isSupported($requested) ? $requested : 'en';

        return Translator::load($locale);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function consent(array $data, Translator $translator): never
    {
        $value = $data['value'] ?? '';
        if ($value !== 'accept' && $value !== 'decline' && $value !== 'reset') {
            throw new DomainError('invalid_options');
        }

        $https = Bootstrap::https();
        if ($value === 'reset') {
            Cookies::clear(Cookies::CONSENT, $https);
            SettingsCookie::clear($https);
        } else {
            Consent::write($value, $https);
            if ($value === 'decline') {
                SettingsCookie::clear($https);
            }
        }
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $messageKey = match ($value) {
            'accept' => 'banner.accept',
            'decline' => 'banner.decline',
            default => 'cookies.change',
        };
        JsonResponse::send(200, [
            'ok' => true,
            'message' => $translator->t($messageKey),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function settings(array $data, Translator $translator): never
    {
        if (Consent::read() !== 'accept') {
            throw new DomainError('consent_required');
        }

        $normalized = SettingsCookie::normalize($data);
        Preset::fromString($normalized['preset'])->assertAllowed('accept');
        SettingsCookie::store($normalized, Bootstrap::https());
        JsonResponse::send(200, ['ok' => true, 'message' => $translator->t('cookies.change')]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function generate(array $data, Translator $translator): never
    {
        $presetRaw = $data['preset'] ?? '';
        if (!is_string($presetRaw)) {
            throw new DomainError('invalid_options');
        }
        $preset = Preset::fromString($presetRaw);
        $consent = Consent::read();
        $preset->assertAllowed($consent);
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        if ($preset === Preset::Custom) {
            $policy = PolicyFactory::fromCustom($options);
        } else {
            $count = $consent === 'accept' ? PolicyFactory::countFrom($options) : 1;
            $policy = PolicyFactory::fromPreset($preset, $count);
        }
        (new RateLimiter())->hit('generate');

        $passwords = (new Generator(Drawer::secure(), new PatternChecker()))->generate($policy);
        $bits = Entropy::bitsForPolicy($policy);
        JsonResponse::send(200, [
            'ok' => true,
            'passwords' => $passwords,
            'bits' => $bits,
            'bitsDisplay' => Entropy::format($bits),
            'verdict' => Entropy::verdict($bits),
            'bitsAreExact' => true,
            'message' => $translator->t('strength.exact'),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function check(array $data, Translator $translator): never
    {
        $password = $data['password'] ?? null;
        if (!is_string($password)) {
            throw new DomainError('invalid_options');
        }
        (new RateLimiter())->hit('check');
        $result = (new Assessor(new PatternChecker()))->assess($password);
        JsonResponse::send(200, [
            'ok' => true,
            'bits' => $result['bits'],
            'bitsDisplay' => Entropy::format($result['bits']),
            'verdict' => $result['verdict'],
            'bitsAreExact' => false,
            'classes' => $result['classes'],
            'findings' => $result['findings'],
            'message' => $translator->t('strength.estimate'),
        ]);
    }
}
