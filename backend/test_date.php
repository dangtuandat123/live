<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$m = new App\Models\LiveEvent(['event_at' => now()]);
echo "Default fromDateTime: " . $m->fromDateTime($m->event_at) . "\n";

class CustomLiveEvent extends App\Models\LiveEvent {
    protected $dateFormat = 'Y-m-d H:i:s.u';
}

$cm = new CustomLiveEvent(['event_at' => now()]);
echo "Custom Y-m-d H:i:s.u fromDateTime: " . $cm->fromDateTime($cm->event_at) . "\n";
