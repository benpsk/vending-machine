<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Request;

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';

if (is_file($projectRoot . '/.env')) {
    Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
}

$kernel = Bootstrap::create($projectRoot);
$kernel->handle(Request::fromGlobals())->send();
