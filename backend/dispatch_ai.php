<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$remaining = App\Models\LiveEvent::where('live_session_id', 4)
    ->where('event_type', 'comment')
    ->where('ai_processed', false)
    ->count();

echo "Remaining unprocessed: {$remaining}\n";

if ($remaining > 0) {
    App\Jobs\AnalyzeCommentsJob::dispatch(4);
    echo "Job dispatched\n";
}
