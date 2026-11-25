<?php

declare(strict_types=1);

require dirname(__DIR__) . '/php/autoload.php';

use Esy\PasswordGenerator\DomainError;
use Esy\PasswordGenerator\Http\RateLimiter;
use Esy\PasswordGenerator\Http\RequestGuard;
use Esy\PasswordGenerator\Http\SettingsCookie;
use Esy\PasswordGenerator\I18n\Locale;
use Esy\PasswordGenerator\Password\Alphabet;
use Esy\PasswordGenerator\Password\Assessor;
use Esy\PasswordGenerator\Password\Drawer;
use Esy\PasswordGenerator\Password\Entropy;
use Esy\PasswordGenerator\Password\Generator;
use Esy\PasswordGenerator\Password\PatternChecker;
use Esy\PasswordGenerator\Password\Policy;
use Esy\PasswordGenerator\Password\PolicyFactory;
use Esy\PasswordGenerator\Password\Preset;

$failures = 0;

function check(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        $failures++;
    }
}

function expectError(callable $action, string $code, string $message): void
{
    try {
        $action();
        check(false, $message);
    } catch (DomainError $error) {
        check($error->errorCode === $code, $message . ' (' . $error->errorCode . ')');
    }
}

check(strlen(Alphabet::LOWER) === 26, 'lowercase size');
check(strlen(Alphabet::UPPER) === 26, 'uppercase size');
check(strlen(Alphabet::DIGITS) === 10, 'digit size');
check(strlen(Alphabet::SPECIAL_BASIC) === 3, 'basic symbol size');
check(strlen(Alphabet::SPECIAL_EXTENDED) === 20, 'extended symbol size');
check(strlen(Alphabet::fullAscii()) === 85, 'full alphabet size');
check(strlen(Alphabet::stripSimilar(Alphabet::fullAscii())) === 78, 'similar filter size');
check(strlen(Alphabet::edges(true)) === 47, 'letter edges after similar filter');

$strongBits = Entropy::bitsForPolicy(PolicyFactory::fromPreset(Preset::Strong));
$veryBits = Entropy::bitsForPolicy(PolicyFactory::fromPreset(Preset::VeryStrong));
$quantumBits = Entropy::bitsForPolicy(PolicyFactory::fromPreset(Preset::Quantum));
check(abs($strongBits - (16 * log(85) / log(2))) < 1e-9, 'strong entropy');
check(Entropy::format($strongBits) === '102.6', 'strong display ' . Entropy::format($strongBits));
check(Entropy::verdict($strongBits) === 'strong', 'strong verdict');
check(abs($veryBits - (20 * log(85) / log(2))) < 1e-9, 'very strong entropy');
check(Entropy::format($veryBits) === '128.2', 'very strong display ' . Entropy::format($veryBits));
check(Entropy::verdict($veryBits) === 'very_strong', 'very strong verdict');
$expectedQuantum = (2 * log(47) / log(2)) + (39 * log(85) / log(2));
check(abs($quantumBits - $expectedQuantum) < 1e-9, 'quantum entropy ' . $quantumBits);
check($quantumBits >= 256, 'quantum entropy reaches the quantum band');
check(Entropy::format($quantumBits) === '261.1', 'quantum display ' . Entropy::format($quantumBits));
check(Entropy::verdict($quantumBits) === 'quantum', 'quantum verdict');

$custom = [
    'lower' => false,
    'upper' => false,
    'digits' => true,
    'specialBasic' => false,
    'specialExtended' => false,
    'similar' => false,
    'edges' => true,
    'length' => 8,
    'count' => 1,
];
$edgePolicy = PolicyFactory::fromCustom($custom);
check($edgePolicy->middleAlphabet === Alphabet::DIGITS, 'edge middle is digits');
check($edgePolicy->edgeAlphabet === Alphabet::LETTERS, 'edge alphabet is letters');
$expectedEdgeBits = (2 * Entropy::log2(52)) + (6 * Entropy::log2(10));
check(abs(Entropy::bitsForPolicy($edgePolicy) - $expectedEdgeBits) < 1e-9, 'edge entropy formula');

$clamped = PolicyFactory::clampedOptions([
    'lower' => true,
    'upper' => true,
    'digits' => true,
    'specialBasic' => true,
    'specialExtended' => true,
    'similar' => false,
    'edges' => false,
    'length' => 1000,
    'count' => 0,
]);
check($clamped['length'] === 128 && $clamped['count'] === 1, 'length and count are clamped');
$presetCount = PolicyFactory::fromPreset(Preset::Strong, 3);
check($presetCount->count === 3 && $presetCount->length === 16, 'preset count is chosen separately');
$presetClamped = PolicyFactory::fromPreset(Preset::Quantum, 1000);
check($presetClamped->count === 25 && $presetClamped->length === 41, 'preset count is clamped');

expectError(
    static fn () => PolicyFactory::fromCustom([
        'lower' => false,
        'upper' => false,
        'digits' => false,
        'specialBasic' => false,
        'specialExtended' => false,
        'similar' => false,
        'edges' => false,
        'length' => 8,
        'count' => 1,
    ]),
    'empty_alphabet',
    'empty alphabet',
);
expectError(static fn () => Preset::fromString('nope'), 'invalid_options', 'unknown preset');
check(!Preset::Strong->requiresConsent() && !Preset::Custom->requiresConsent(), 'open presets');
check(Preset::VeryStrong->requiresConsent() && Preset::Quantum->requiresConsent(), 'premium presets');
expectError(static fn () => Preset::Quantum->assertAllowed(null), 'consent_required', 'quantum without consent');
expectError(static fn () => Preset::VeryStrong->assertAllowed('decline'), 'consent_required', 'very strong after decline');
Preset::Strong->assertAllowed(null);
Preset::Quantum->assertAllowed('accept');

$patterns = new PatternChecker();
$generator = new Generator(Drawer::secure(), $patterns);

$quantumPassword = $generator->generate(PolicyFactory::fromPreset(Preset::Quantum))[0];
check(strlen($quantumPassword) === 41, 'quantum password length');
$quantumEdges = Alphabet::edges(true);
check(
    str_contains($quantumEdges, $quantumPassword[0]) && str_contains($quantumEdges, $quantumPassword[40]),
    'quantum edges are letters without lookalikes',
);
$quantumMiddle = true;
foreach (str_split(substr($quantumPassword, 1, 39)) as $character) {
    if (!str_contains(Alphabet::fullAscii(), $character)) {
        $quantumMiddle = false;
    }
}
check($quantumMiddle, 'quantum middle stays in the ASCII alphabet');
check($patterns->findings($quantumPassword) === [], 'quantum password has no pattern hit');

foreach ($generator->generate(PolicyFactory::fromPreset(Preset::Strong)) as $password) {
    check(strlen($password) === 16, 'strong length');
    $within = true;
    foreach (str_split($password) as $character) {
        if (!str_contains(Alphabet::fullAscii(), $character)) {
            $within = false;
        }
    }
    check($within, 'strong password stays in the ASCII alphabet');
    check($patterns->findings($password) === [], 'strong password has no pattern hit');
}

foreach ($generator->generate($edgePolicy) as $password) {
    check(strlen($password) === 8, 'edged password length');
    check(str_contains(Alphabet::LETTERS, $password[0]) && str_contains(Alphabet::LETTERS, $password[7]), 'edges are letters');
    $middleOk = true;
    foreach (str_split(substr($password, 1, 6)) as $character) {
        if (!str_contains(Alphabet::DIGITS, $character)) {
            $middleOk = false;
        }
    }
    check($middleOk, 'edged middle is digits');
}

expectError(
    static fn () => $generator->generate(new Policy(Preset::Custom, 'a', '', 8, 1, false)),
    'pattern_exhausted',
    'repeated draws give up',
);

function codesOf(string $password): array
{
    $codes = [];
    foreach ((new PatternChecker())->findings($password) as $finding) {
        $codes[$finding['code']] = $finding['match'];
    }

    return $codes;
}

$qwerty = codesOf('zzqwertyzz');
check(isset($qwerty['keyboard']), 'qwerty is a keyboard walk');
$digits = codesOf('xx1234yy');
check(isset($digits['sequence']), '1234 is a sequence');
$passwordHit = codesOf('xxpasswordyy');
check(isset($passwordHit['dictionary']) && $passwordHit['dictionary'] === 'password', 'password is a dictionary hit');
$leet = codesOf('p@ssw0rd');
check(isset($leet['dictionary']), 'leet password is a dictionary hit');
$special = codesOf('abc!@#xyz');
check(isset($special['keyboard']) && $special['keyboard'] === '!@#', '!@# is a keyboard walk');
$repeat = codesOf('xxaaaayy');
check(isset($repeat['repeat']) && $repeat['repeat'] === 'aaaa', 'aaaa is a repeat');
$sequence = codesOf('ZZabcdYY');
check(isset($sequence['sequence']) && $sequence['sequence'] === 'abcd', 'abcd is a sequence');
$ideographs = codesOf('的一是不');
check(!isset($ideographs['sequence']) && !isset($ideographs['keyboard']) && !isset($ideographs['repeat']), 'ideographs are not an ASCII sequence');

$assessor = new Assessor(new PatternChecker());
$checked = $assessor->assess('password');
check($checked['verdict'] === 'weak' || $checked['verdict'] === 'very_weak', 'common password is not rated above weak');
check($checked['classes']['lower'] === true, 'password uses lowercase');
expectError(static fn () => $assessor->assess(''), 'empty_password', 'empty check');
expectError(static fn () => $assessor->assess(str_repeat('a', 257)), 'too_long', 'overlong check');

$locales = ['en', 'pl', 'fr', 'es', 'zh'];
$keys = null;
foreach ($locales as $locale) {
    $messages = require dirname(__DIR__) . '/lang/' . $locale . '.php';
    $current = array_keys($messages);
    sort($current);
    check($messages !== [] && !in_array('', $messages, true), $locale . ' translations are filled');
    if ($keys === null) {
        $keys = $current;
    } else {
        check($keys === $current, $locale . ' has the same translation keys');
    }
}

check(Locale::resolve(null, null, false, null) === 'en', 'missing language header is English');
check(Locale::resolve(null, null, false, 'pl-PL,pl;q=0.9,en;q=0.8') === 'pl', 'Polish Accept-Language');
check(Locale::resolve(null, null, false, 'zh-TW') === 'en', 'traditional Chinese falls back to English');
check(Locale::resolve(null, null, false, 'zh-TW,zh;q=0.8') === 'zh', 'generic Chinese still matches');
check(Locale::resolve(null, null, false, 'de-DE,de;q=0.9') === 'en', 'other languages fall back to English');
check(Locale::resolve('fr', 'en', true, 'pl') === 'fr', 'query language wins');
check(Locale::resolve(null, 'es', true, 'pl') === 'es', 'saved language is used after consent');
check(Locale::resolve(null, 'es', false, 'pl') === 'pl', 'saved language is ignored without consent');
check(Locale::resolve('nope', null, false, null) === 'en', 'unknown query language is English');

$savedServer = $_SERVER;
$_SERVER['HTTPS'] = '';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = '';
$_SERVER['HTTP_HOST'] = 'esy.pl';
$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
unset($_SERVER['HTTP_SEC_FETCH_SITE'], $_SERVER['HTTP_REFERER']);
expectError(static fn () => RequestGuard::assertOrigin(), 'origin', 'foreign origin');
$_SERVER['HTTP_ORIGIN'] = 'https://esy.pl';
RequestGuard::assertOrigin();
unset($_SERVER['HTTP_ORIGIN']);
$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
expectError(static fn () => RequestGuard::assertOrigin(), 'origin', 'cross-site fetch');
$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
RequestGuard::assertOrigin();
unset($_SERVER['HTTP_SEC_FETCH_SITE']);
$_SERVER['HTTP_REFERER'] = 'https://esy.pl/password-generator/?lang=pl';
RequestGuard::assertOrigin();
$_SERVER['HTTP_REFERER'] = 'https://evil.example/';
expectError(static fn () => RequestGuard::assertOrigin(), 'origin', 'foreign referer');
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['HTTP_ORIGIN'] = 'http://127.0.0.1:8765';
unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_SEC_FETCH_SITE']);
RequestGuard::assertOrigin();
$_SERVER['HTTP_HOST'] = 'example.test';
expectError(static fn () => RequestGuard::assertOrigin(), 'origin', 'unknown host');
$_SERVER = $savedServer;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}
$_SESSION = [];
$limiter = new RateLimiter();
for ($index = 0; $index < 60; $index++) {
    $limiter->hit('generate');
}
expectError(static fn () => $limiter->hit('generate'), 'rate_limited', 'session rate limit');
$_SESSION['csrf'] = str_repeat('ab', 32);
$_SERVER['HTTP_X_CSRF_TOKEN'] = str_repeat('cd', 32);
expectError(static fn () => RequestGuard::assertCsrf(), 'csrf', 'wrong csrf token');
$_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf'];
RequestGuard::assertCsrf();

$normalized = SettingsCookie::normalize([
    'lang' => 'pl',
    'preset' => 'custom',
    'options' => [
        'lower' => true,
        'upper' => false,
        'digits' => true,
        'specialBasic' => false,
        'specialExtended' => false,
        'similar' => true,
        'edges' => false,
        'length' => 4,
        'count' => 40,
    ],
]);
check($normalized['length'] === 8 && $normalized['count'] === 25 && $normalized['lang'] === 'pl', 'settings are normalized');

if ($failures > 0) {
    fwrite(STDERR, $failures . " failed\n");
    exit(1);
}

fwrite(STDOUT, "ok\n");
