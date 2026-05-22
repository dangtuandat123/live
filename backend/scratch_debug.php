<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "CACHE TOP KEYWORDS:\n";
print_r(\Illuminate\Support\Facades\Cache::get("live_session_2_top_keywords"));
echo "\n";
