<?php

declare(strict_types=1);

if (!extension_loaded('intl')) {
    fwrite(STDERR, "The intl extension is required to build the quantum alphabet.\n");
    exit(1);
}

$dataDir = dirname(__DIR__) . '/data';
$confusableMap = loadConfusables($dataDir . '/confusables.txt');
$forbidden = [];
foreach (str_split('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz') as $ascii) {
    $forbidden[skeleton($ascii, $confusableMap)] = true;
}

$handle = fopen($dataDir . '/hanzi_db.csv', 'rb');
if ($handle === false) {
    fwrite(STDERR, "Cannot read hanzi_db.csv.\n");
    exit(1);
}

$header = fgetcsv($handle, null, ',', '"', '');
if (!is_array($header)) {
    fwrite(STDERR, "hanzi_db.csv has no header.\n");
    exit(1);
}
if (isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
}
$rankIndex = array_search('frequency_rank', $header, true);
$charIndex = array_search('character', $header, true);
if ($rankIndex === false || $charIndex === false) {
    fwrite(STDERR, "hanzi_db.csv is missing frequency_rank or character.\n");
    exit(1);
}

$rows = [];
while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
    $character = (string) ($row[$charIndex] ?? '');
    $rank = (int) ($row[$rankIndex] ?? 0);
    if ($character === '' || $rank <= 0) {
        continue;
    }
    $rows[] = [$rank, $character];
}
fclose($handle);

usort($rows, static function (array $left, array $right): int {
    return $left[0] <=> $right[0];
});

$rejectedCategories = [
    IntlChar::CHAR_CATEGORY_UNASSIGNED,
    IntlChar::CHAR_CATEGORY_CONTROL_CHAR,
    IntlChar::CHAR_CATEGORY_FORMAT_CHAR,
    IntlChar::CHAR_CATEGORY_PRIVATE_USE_CHAR,
    IntlChar::CHAR_CATEGORY_SURROGATE,
    IntlChar::CHAR_CATEGORY_LINE_SEPARATOR,
    IntlChar::CHAR_CATEGORY_PARAGRAPH_SEPARATOR,
    IntlChar::CHAR_CATEGORY_SPACE_SEPARATOR,
    IntlChar::CHAR_CATEGORY_NON_SPACING_MARK,
    IntlChar::CHAR_CATEGORY_ENCLOSING_MARK,
    IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK,
];

$accepted = [];
$usedSkeletons = [];
foreach ($rows as [$rank, $character]) {
    if (count($accepted) >= 2048) {
        break;
    }
    if (isset($accepted[$character])) {
        continue;
    }
    $parts = preg_split('//u', $character, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false || count($parts) !== 1) {
        continue;
    }
    $character = $parts[0];
    if (strlen($character) !== 3) {
        continue;
    }
    $codepoint = IntlChar::ord($character);
    if (!is_int($codepoint) || $codepoint < 0x4E00 || $codepoint > 0x9FFF) {
        continue;
    }
    if (in_array(IntlChar::charType($codepoint), $rejectedCategories, true)) {
        continue;
    }
    if (IntlChar::hasBinaryProperty($codepoint, IntlChar::PROPERTY_DEFAULT_IGNORABLE_CODE_POINT)) {
        continue;
    }
    $nfkc = Normalizer::normalize($character, Normalizer::FORM_KC);
    $nfd = Normalizer::normalize($character, Normalizer::FORM_D);
    if ($nfkc !== $character || $nfd !== $character) {
        continue;
    }

    $shape = skeleton($character, $confusableMap);
    if ($shape === '' || preg_match('/[0-9A-Za-z]/', $shape) === 1) {
        continue;
    }
    if (isset($forbidden[$shape]) || isset($usedSkeletons[$shape])) {
        continue;
    }

    $accepted[$character] = true;
    $usedSkeletons[$shape] = true;
}

$characters = array_keys($accepted);
if (count($characters) !== 2048) {
    fwrite(STDERR, 'Only ' . count($characters) . " ideographs passed the filter.\n");
    exit(1);
}

$unicodeLicense = trim((string) file_get_contents($dataDir . '/UNICODE-LICENSE.txt'));
$hanziLicense = trim((string) file_get_contents($dataDir . '/hanzi-LICENSE.txt'));
$comment = "2048 simplified Chinese ideographs for the quantum-secure preset.\n"
    . "Frequency order uses column frequency_rank from hanzi_db.csv (1 = most common).\n"
    . "Confusables: Unicode Security Mechanisms UTS #39, version 18.0.0.\n\n"
    . "hanzi_db.csv license:\n" . $hanziLicense . "\n\n"
    . "Unicode confusables license:\n" . $unicodeLicense;

$header = "<?php\n\ndeclare(strict_types=1);\n\n/**\n";
foreach (preg_split("/\r\n|\n|\r/", $comment) ?: [] as $line) {
    $header .= ' * ' . rtrim($line) . "\n";
}
$header .= " */\nreturn ";

$exported = var_export(array_values($characters), true);
if (!is_string($exported)) {
    fwrite(STDERR, "Could not export the alphabet.\n");
    exit(1);
}

file_put_contents($dataDir . '/quantum-alphabet.php', $header . $exported . ";\n");
fwrite(STDOUT, "2048 ideographs\n");

/**
 * @return array<string, string>
 */
function loadConfusables(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fwrite(STDERR, "Cannot read confusables.txt.\n");
        exit(1);
    }

    $map = [];
    foreach ($lines as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $hash = strpos($line, '#');
        if ($hash !== false) {
            $line = substr($line, 0, $hash);
        }
        $fields = explode(';', $line);
        if (count($fields) < 2) {
            continue;
        }
        $source = $fields[0] |> trim(...) |> hexToUtf8(...);
        $target = $fields[1] |> trim(...) |> hexToUtf8(...);
        if ($source === '' || $target === '') {
            continue;
        }
        $map[$source] = $target;
    }

    return $map;
}

function hexToUtf8(string $hexList): string
{
    $text = '';
    foreach (preg_split('/\s+/', $hexList) ?: [] as $hex) {
        if ($hex === '') {
            continue;
        }
        if (preg_match('/^[0-9A-Fa-f]{4,6}$/', $hex) !== 1) {
            return '';
        }
        $character = IntlChar::chr(hexdec($hex));
        if (!is_string($character)) {
            return '';
        }
        $text .= $character;
    }

    return $text;
}

/**
 * @param array<string, string> $map
 */
function skeleton(string $character, array $map): string
{
    $nfd = Normalizer::normalize($character, Normalizer::FORM_D);
    if (!is_string($nfd)) {
        return $character;
    }

    $pieces = preg_split('//u', $nfd, -1, PREG_SPLIT_NO_EMPTY);
    if ($pieces === false) {
        return $character;
    }

    $mapped = '';
    foreach ($pieces as $piece) {
        $codepoint = IntlChar::ord($piece);
        if (is_int($codepoint) && IntlChar::hasBinaryProperty($codepoint, IntlChar::PROPERTY_DEFAULT_IGNORABLE_CODE_POINT)) {
            continue;
        }
        $mapped .= $map[$piece] ?? $piece;
    }

    $again = Normalizer::normalize($mapped, Normalizer::FORM_D);

    return is_string($again) ? $again : $mapped;
}
