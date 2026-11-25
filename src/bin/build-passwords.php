<?php

declare(strict_types=1);

$dataDir = dirname(__DIR__) . '/data';
$source = $dataDir . '/10k-most-common.txt';
$lines = file($source, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Cannot read the password list.\n");
    exit(1);
}

$seen = [];
$words = [];
foreach ($lines as $line) {
    $word = strtr(trim($line), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    if ($word === '' || strlen($word) < 4 || isset($seen['k' . $word])) {
        continue;
    }
    $seen['k' . $word] = true;
    $words[] = $word;
}

foreach (['qwerty', 'azerty', 'asdf', 'zxcv', '1234'] as $extra) {
    if (!isset($seen['k' . $extra])) {
        $seen['k' . $extra] = true;
        $words[] = $extra;
    }
}

$license = trim((string) file_get_contents($dataDir . '/seclists-LICENSE.txt'));
$comment = "Common-password fragments.\n\n"
    . "Source: danielmiessler/SecLists Passwords/Common-Credentials/10k-most-common.txt\n"
    . "(the 10k list compiled by Mark Burnett), plus qwerty, azerty, asdf, zxcv and 1234.\n"
    . "Entries shorter than 4 characters are omitted.\n\n"
    . $license;

$header = "<?php\n\ndeclare(strict_types=1);\n\n/**\n";
foreach (preg_split("/\r\n|\n|\r/", $comment) ?: [] as $line) {
    $header .= ' * ' . rtrim($line) . "\n";
}
$header .= " */\nreturn ";

$body = var_export($words, true);
if (!is_string($body)) {
    fwrite(STDERR, "Could not export the password list.\n");
    exit(1);
}

file_put_contents($dataDir . '/common-passwords.php', $header . $body . ";\n");
fwrite(STDOUT, count($words) . " passwords\n");
