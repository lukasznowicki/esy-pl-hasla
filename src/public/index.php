<?php

declare(strict_types=1);

require __DIR__ . '/lib/autoload.php';

use Esy\PasswordGenerator\Bootstrap;
use Esy\PasswordGenerator\Http\Page;

Bootstrap::boot();
Page::emit();
