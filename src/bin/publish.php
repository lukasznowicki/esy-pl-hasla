<?php

declare(strict_types=1);

$src = dirname(__DIR__);
$repo = dirname($src);
$out = $repo . '/password-generator';

copyTree($src . '/php', $out . '/lib');
copyTree($src . '/lang', $out . '/lang');

if (!is_dir($out . '/data') && !mkdir($out . '/data', 0775, true) && !is_dir($out . '/data')) {
    fwrite(STDERR, "Cannot create the data directory.\n");
    exit(1);
}
if (!is_dir($out . '/assets') && !mkdir($out . '/assets', 0775, true) && !is_dir($out . '/assets')) {
    fwrite(STDERR, "Cannot create the assets directory.\n");
    exit(1);
}

$staleAlphabet = $out . '/data/quantum-alphabet.php';
if (is_file($staleAlphabet) && !unlink($staleAlphabet)) {
    fwrite(STDERR, "Cannot remove the old quantum alphabet.\n");
    exit(1);
}

foreach (['common-passwords.php'] as $dataFile) {
    if (!is_file($src . '/data/' . $dataFile)) {
        fwrite(STDERR, "Missing src/data/{$dataFile}. Run the data build first.\n");
        exit(1);
    }
    if (!copy($src . '/data/' . $dataFile, $out . '/data/' . $dataFile)) {
        fwrite(STDERR, "Cannot copy {$dataFile}.\n");
        exit(1);
    }
}

foreach (['index.php', 'api.php'] as $entry) {
    if (!copy($src . '/public/' . $entry, $out . '/' . $entry)) {
        fwrite(STDERR, "Cannot copy {$entry}.\n");
        exit(1);
    }
}

$deny = "Require all denied\n";
file_put_contents($out . '/lib/.htaccess', $deny);
file_put_contents($out . '/lang/.htaccess', $deny);
file_put_contents($out . '/data/.htaccess', $deny);
file_put_contents($out . '/.htaccess', <<<'HTACCESS'
DirectoryIndex index.php
Options -Indexes

<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
HTACCESS);

$hasla = $repo . '/hasla';
if (!is_dir($hasla) && !mkdir($hasla, 0775, true) && !is_dir($hasla)) {
    fwrite(STDERR, "Cannot create hasla/.\n");
    exit(1);
}
file_put_contents($hasla . '/.htaccess', <<<'HTACCESS'
RewriteEngine On
RewriteRule ^ https://esy.pl/password-generator/? [R=301,L]
HTACCESS);

fwrite(STDOUT, "Published password-generator/ and hasla/.htaccess\n");

function copyTree(string $from, string $to): void
{
    if (!is_dir($to) && !mkdir($to, 0775, true) && !is_dir($to)) {
        fwrite(STDERR, "Cannot create {$to}.\n");
        exit(1);
    }

    $items = scandir($from);
    if ($items === false) {
        fwrite(STDERR, "Cannot read {$from}.\n");
        exit(1);
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $source = $from . DIRECTORY_SEPARATOR . $item;
        $target = $to . DIRECTORY_SEPARATOR . $item;
        if (is_dir($source)) {
            copyTree($source, $target);
            continue;
        }
        if (!copy($source, $target)) {
            fwrite(STDERR, "Cannot copy {$source}.\n");
            exit(1);
        }
    }
}
