<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uri) || $uri === '') {
    $uri = '/';
}

$prefix = '/password-generator';
if ($uri === $prefix || $uri === $prefix . '/') {
    require $root . '/password-generator/index.php';
    return;
}

if (str_starts_with($uri, $prefix . '/')) {
    $relative = substr($uri, strlen($prefix) + 1);
    if ($relative === 'index.php') {
        require $root . '/password-generator/index.php';
        return;
    }
    if ($relative === 'api.php') {
        require $root . '/password-generator/api.php';
        return;
    }
    $file = $root . $prefix . '/' . $relative;
    if (is_file($file)) {
        return false;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found\n";
