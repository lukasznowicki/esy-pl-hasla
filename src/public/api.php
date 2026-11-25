<?php

declare(strict_types=1);

require __DIR__ . '/lib/autoload.php';

use Esy\PasswordGenerator\Bootstrap;
use Esy\PasswordGenerator\DomainError;
use Esy\PasswordGenerator\Http\FrontController;
use Esy\PasswordGenerator\Http\JsonResponse;
use Esy\PasswordGenerator\I18n\Translator;

Bootstrap::boot();

try {
    FrontController::handle();
} catch (DomainError $error) {
    JsonResponse::fail($error->errorCode, Translator::load('en'));
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"ok":false,"error":"generic","message":"Something went wrong. Try again."}';
}
