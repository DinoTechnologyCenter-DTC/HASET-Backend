<?php
// auto_approve.php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Watching for new transactions...\n";

while (true) {
    $transaction = \App\Models\Transaction::where('status', 'processing')->latest()->first();
    if ($transaction) {
        echo "Auto-approving Transaction ID: " . $transaction->id . "\n";
        $transaction->status = 'success';
        $transaction->save();
    }
    sleep(2);
}
