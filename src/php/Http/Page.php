<?php

declare(strict_types=1);

namespace Esy\PasswordGenerator\Http;

use Esy\PasswordGenerator\Bootstrap;
use Esy\PasswordGenerator\DomainError;
use Esy\PasswordGenerator\I18n\Locale;
use Esy\PasswordGenerator\I18n\Translator;
use Esy\PasswordGenerator\Password\Alphabet;
use Esy\PasswordGenerator\Password\Entropy;
use Esy\PasswordGenerator\Password\PolicyFactory;
use Esy\PasswordGenerator\Password\Preset;
use Esy\PasswordGenerator\Paths;

final class Page
{
    public static function emit(): void
    {
        $https = Bootstrap::https();
        $consent = Consent::read();
        $accepted = $consent === 'accept';
        $stored = $accepted ? SettingsCookie::read() : null;
        $query = $_GET['lang'] ?? null;
        $queryLang = is_string($query) ? $query : null;

        if ($accepted && $queryLang !== null && Locale::isSupported($queryLang)) {
            $current = $stored ?? SettingsCookie::defaults();
            if (($current['lang'] ?? '') !== $queryLang) {
                $current['lang'] = $queryLang;
                SettingsCookie::store($current, $https);
                $stored = $current;
            }
        }

        $cookieLang = isset($stored['lang']) && is_string($stored['lang']) ? $stored['lang'] : null;
        $locale = Locale::resolve(
            $queryLang,
            $cookieLang,
            $accepted,
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'])
                ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                : null,
        );
        $translator = Translator::load($locale);
        $preset = self::selectedPreset($accepted, $stored);
        $custom = self::customOptions($stored);
        [$bits, $verdict] = self::initialStrength($preset, $custom);
        $bitsDisplay = Entropy::format($bits);
        $shown = self::shownOptions($preset, $custom);
        $width = min(100, ($bits / 256) * 100);

        $root = Paths::root();
        $cssVersion = is_file($root . '/assets/app.css') ? (string) filemtime($root . '/assets/app.css') : '1';
        $jsVersion = is_file($root . '/assets/app.js') ? (string) filemtime($root . '/assets/app.js') : '1';
        $config = [
            'csrf' => Bootstrap::csrf(),
            'lang' => $locale,
            'consent' => $accepted,
            'api' => 'api.php',
            'preset' => $preset->value,
            'limits' => [
                'length' => ['min' => PolicyFactory::MIN_LENGTH, 'max' => PolicyFactory::MAX_LENGTH],
                'count' => ['min' => PolicyFactory::MIN_COUNT, 'max' => PolicyFactory::MAX_COUNT],
            ],
            'alphabets' => [
                'lower' => Alphabet::LOWER,
                'upper' => Alphabet::UPPER,
                'digits' => Alphabet::DIGITS,
                'specialBasic' => Alphabet::SPECIAL_BASIC,
                'specialExtended' => Alphabet::SPECIAL_EXTENDED,
                'similar' => Alphabet::SIMILAR,
            ],
            'presets' => self::presetCatalog(),
            'custom' => $custom,
            'i18n' => $translator->all(),
        ];
        $configJson = json_encode(
            $config,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-cache');
        $e = htmlspecialchars(...);
        $htmlLang = Locale::htmlLang($locale);
        $optionsDisabled = $preset !== Preset::Custom;
        $showQuantum = $preset === Preset::Quantum;
        $bannerHidden = $consent !== null;
        ?>
<!doctype html>
<html lang="<?= $e($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($translator->t('meta.title')) ?></title>
    <meta name="description" content="<?= $e($translator->t('meta.description')) ?>">
    <?php foreach (['en' => 'en', 'pl' => 'pl', 'fr' => 'fr', 'es' => 'es', 'zh' => 'zh-CN'] as $code => $hrefLang): ?>
        <link rel="alternate" hreflang="<?= $e($hrefLang) ?>" href="?lang=<?= $e($code) ?>">
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="?lang=en">
    <link rel="stylesheet" href="assets/app.css?v=<?= $e($cssVersion) ?>">
    <?php if ($accepted): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-F8B339L3PF"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-F8B339L3PF');
        </script>
        <script data-ad-client="ca-pub-4437248364361538" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
    <?php endif; ?>
</head>
<body>
<div id="generator">
    <div id="contentWrapper">
        <nav class="langs" aria-label="<?= $e($translator->t('lang.label')) ?>">
            <?php foreach (self::languageLinks() as $code => $label): ?>
                <a href="?lang=<?= $e($code) ?>"<?= $code === $locale ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <h1><?= $e($translator->t('heading')) ?></h1>
        <p class="copyright">&copy;1996-<?= date('Y') ?>
            <a href="https://phylax.pl/">phylax.pl</a>
            <a href="https://lukasznowicki.info/">Łukasz Nowicki</a>
            <a class="coffee" href="https://buymeacoffee.com/grubasfit"><?= $e($translator->t('coffee')) ?></a>
        </p>

        <section id="cookie" class="banner"<?= $bannerHidden ? ' hidden' : '' ?>>
            <p><?= $e($translator->t('banner.p1')) ?></p>
            <p><?= $e($translator->t('banner.p2')) ?></p>
            <p><?= $e($translator->t('banner.p3')) ?></p>
            <p><?= $e($translator->t('banner.p4')) ?></p>
            <div class="banner-actions">
                <button type="button" class="button" id="cookieAccept"><?= $e($translator->t('banner.accept')) ?></button>
                <button type="button" class="button" id="cookieDecline"><?= $e($translator->t('banner.decline')) ?></button>
            </div>
        </section>

        <fieldset class="presets">
            <legend><?= $e($translator->t('preset.legend')) ?></legend>
            <?php foreach (self::presetChoices() as $value => $key): ?>
                <?php $choice = Preset::fromString($value); ?>
                <label<?= !$accepted && $choice->requiresConsent() ? ' title="' . $e($translator->t('preset.locked')) . '"' : '' ?>>
                    <input type="radio" name="preset" value="<?= $e($value) ?>"<?= $preset === $choice ? ' checked' : '' ?><?= !$accepted && $choice->requiresConsent() ? ' disabled' : '' ?>>
                    <?= $e($translator->t($key)) ?>
                </label>
            <?php endforeach; ?>
            <p id="quantumSummary"<?= $showQuantum ? '' : ' hidden' ?>><?= $e($translator->t('quantum.summary')) ?></p>
            <p id="quantumNote" class="note"<?= $showQuantum ? '' : ' hidden' ?>><?= $e($translator->t('quantum.note')) ?></p>
        </fieldset>

        <div class="strength" id="passStrength">
            <div class="strength-fill" id="psScroll" style="width: <?= $e((string) $width) ?>%"></div>
            <div class="strength-label" id="psValue"><?= $e($translator->t('strength.bits', ['bits' => $bitsDisplay])) ?> · <?= $e($translator->t('verdict.' . $verdict)) ?></div>
        </div>
        <p class="hint" id="strengthHint"><?= $e($translator->t('strength.exact')) ?></p>

        <fieldset id="options"<?= $optionsDisabled ? ' disabled' : '' ?><?= $showQuantum ? ' hidden' : '' ?>>
            <legend><?= $e($translator->t('options.legend')) ?></legend>
            <label><input id="peLowerCase" type="checkbox"<?= $shown['lower'] ? ' checked' : '' ?>> <?= $e($translator->t('opt.lower')) ?></label>
            <label><input id="peUpperCase" type="checkbox"<?= $shown['upper'] ? ' checked' : '' ?>> <?= $e($translator->t('opt.upper')) ?></label>
            <label><input id="peDigits" type="checkbox"<?= $shown['digits'] ? ' checked' : '' ?>> <?= $e($translator->t('opt.digits')) ?></label>
            <label><input id="peSpecial1" type="checkbox"<?= $shown['specialBasic'] ? ' checked' : '' ?>> <?= $e($translator->t('opt.special_basic')) ?></label>
            <label><input id="peSpecial2" type="checkbox"<?= $shown['specialExtended'] ? ' checked' : '' ?>> <?= $e($translator->t('opt.special_extended')) ?></label>
            <label><input id="peSimilar" type="checkbox"<?= $shown['similar'] ? ' checked' : '' ?>> <?= $e($translator->t('opt.similar')) ?></label>
            <label><input id="peEdges" type="checkbox"<?= $shown['edges'] ? ' checked' : '' ?>> <?= $e($translator->t('opt.edges')) ?></label>
            <div class="sliders">
                <label for="goLength"><?= $e($translator->t('opt.length')) ?> (<span id="goLengthValue"><?= $shown['length'] ?></span>)
                    <input id="goLength" type="range" min="<?= PolicyFactory::MIN_LENGTH ?>" max="<?= PolicyFactory::MAX_LENGTH ?>" step="1" value="<?= $shown['length'] ?>">
                </label>
            </div>
        </fieldset>
        <label class="count-control" for="goCount"><?= $e($translator->t('opt.count')) ?> (<span id="goCountValue"><?= $custom['count'] ?></span>)
            <input id="goCount" type="range" min="<?= PolicyFactory::MIN_COUNT ?>" max="<?= PolicyFactory::MAX_COUNT ?>" step="1" value="<?= $custom['count'] ?>"<?= !$accepted && $preset !== Preset::Custom ? ' disabled' : '' ?>>
        </label>

        <div class="result" id="generatedPasswords" hidden>
            <button type="button" id="copyList"><?= $e($translator->t('copy')) ?></button>
            <label class="visually-hidden" for="listPasswords"><?= $e($translator->t('result.label')) ?></label>
            <textarea id="listPasswords" rows="1" spellcheck="false" autocomplete="off" autocapitalize="off"></textarea>
            <p id="copyStatus" aria-live="polite"></p>
        </div>
        <p id="formError" class="error" role="alert"></p>
        <div class="actions">
            <button type="button" class="button" id="doGenerate"><?= $e($translator->t($custom['count'] > 1 ? 'generate.many' : 'generate.one')) ?></button>
        </div>

        <form id="checker" novalidate>
            <fieldset>
                <legend><?= $e($translator->t('check.legend')) ?></legend>
                <label for="checkPassword"><?= $e($translator->t('check.label')) ?></label>
                <input id="checkPassword" name="check-secret" type="password" autocomplete="off" spellcheck="false" autocapitalize="off">
                <div class="actions">
                    <button type="button" id="checkToggle"><?= $e($translator->t('check.show')) ?></button>
                    <button type="submit" class="button" id="doCheck"><?= $e($translator->t('check.button')) ?></button>
                </div>
                <div id="checkResult" aria-live="polite"></div>
            </fieldset>
        </form>

        <?php if ($consent !== null): ?>
            <p class="cookies-link"><button type="button" id="cookiesLink"><?= $e($translator->t('cookies.change')) ?></button></p>
        <?php endif; ?>
        <noscript><p><?= $e($translator->t('noscript')) ?></p></noscript>
    </div>
</div>
<script type="application/json" id="pg-config"><?= $configJson ?></script>
<script src="assets/app.js?v=<?= $e($jsVersion) ?>" defer></script>
</body>
</html>
        <?php
    }

    /**
     * @param array<string, bool|int|string>|null $stored
     */
    private static function selectedPreset(bool $accepted, ?array $stored): Preset
    {
        if (!$accepted) {
            return Preset::Strong;
        }
        $saved = $stored['preset'] ?? null;
        if (!is_string($saved)) {
            return Preset::Quantum;
        }

        return Preset::tryFrom($saved) ?? Preset::Quantum;
    }

    /**
     * @param array<string, bool|int|string>|null $stored
     * @return array{
     *     lower: bool, upper: bool, digits: bool, specialBasic: bool, specialExtended: bool,
     *     similar: bool, edges: bool, length: int, count: int
     * }
     */
    private static function customOptions(?array $stored): array
    {
        $defaults = SettingsCookie::defaults();
        $source = $stored ?? $defaults;

        return [
            'lower' => self::boolValue($source, 'lower', true),
            'upper' => self::boolValue($source, 'upper', true),
            'digits' => self::boolValue($source, 'digits', true),
            'specialBasic' => self::boolValue($source, 'specialBasic', true),
            'specialExtended' => self::boolValue($source, 'specialExtended', true),
            'similar' => self::boolValue($source, 'similar', false),
            'edges' => self::boolValue($source, 'edges', false),
            'length' => self::intValue($source, 'length', 16),
            'count' => self::intValue($source, 'count', 1),
        ];
    }

    /**
     * @param array<string, bool|int> $custom
     * @return array{0: float, 1: string}
     */
    private static function initialStrength(Preset $preset, array $custom): array
    {
        try {
            $policy = $preset === Preset::Custom
                ? PolicyFactory::fromCustom($custom)
                : PolicyFactory::fromPreset($preset);
            $bits = Entropy::bitsForPolicy($policy);

            return [$bits, Entropy::verdict($bits)];
        } catch (DomainError) {
            return [0.0, 'very_weak'];
        }
    }

    /**
     * @param array<string, bool|int> $custom
     * @return array<string, bool|int>
     */
    private static function shownOptions(Preset $preset, array $custom): array
    {
        if ($preset === Preset::Custom) {
            return $custom;
        }

        $shown = [
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
        $policy = PolicyFactory::fromPreset($preset);
        $shown['length'] = $policy->length;
        $shown['count'] = $policy->count;

        return $shown;
    }

    /**
     * @return array<string, array{length: int, count: int, bits: float, bitsDisplay: string, verdict: string}>
     */
    private static function presetCatalog(): array
    {
        $catalog = [];
        foreach ([Preset::Strong, Preset::VeryStrong, Preset::Quantum] as $preset) {
            $policy = PolicyFactory::fromPreset($preset);
            $bits = Entropy::bitsForPolicy($policy);
            $catalog[$preset->value] = [
                'length' => $policy->length,
                'count' => $policy->count,
                'bits' => $bits,
                'bitsDisplay' => Entropy::format($bits),
                'verdict' => Entropy::verdict($bits),
            ];
        }

        return $catalog;
    }

    /**
     * @return array<string, string>
     */
    private static function languageLinks(): array
    {
        return [
            'en' => 'English',
            'pl' => 'Polski',
            'fr' => 'Français',
            'es' => 'Español',
            'zh' => '中文',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function presetChoices(): array
    {
        return [
            Preset::Custom->value => 'preset.custom',
            Preset::Strong->value => 'preset.strong',
            Preset::VeryStrong->value => 'preset.very_strong',
            Preset::Quantum->value => 'preset.quantum',
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function boolValue(array $source, string $key, bool $fallback): bool
    {
        $value = $source[$key] ?? $fallback;

        return is_bool($value) ? $value : $fallback;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function intValue(array $source, string $key, int $fallback): int
    {
        $value = $source[$key] ?? $fallback;

        return is_int($value) ? $value : $fallback;
    }
}
