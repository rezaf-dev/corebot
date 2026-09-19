<?php

use Illuminate\Contracts\Console\Kernel;

function defer(): never
{
    throw new RuntimeException('The global defer function was called.');
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Illuminate\Support\defer(static fn () => null);

echo 'laravel-defer-ok';
