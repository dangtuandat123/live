<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Active Queue Driver: " . config('queue.default') . "\n";
echo "Resolved APP_URL: " . config('app.url') . "\n";
echo "Cache Store: " . config('cache.default') . "\n";
