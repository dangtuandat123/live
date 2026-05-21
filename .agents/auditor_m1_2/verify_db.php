<?php

// Boot Laravel
require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\SubscriptionPackage;
use App\Models\UserSubscription;
use App\Models\PaymentConfig;
use App\Models\Transaction;

echo "=== DATABASE SCHEMA FORENSIC INSPECTION ===\n\n";

$tables = ['subscription_packages', 'user_subscriptions', 'payment_configs', 'transactions'];

foreach ($tables as $table) {
    if (!Schema::hasTable($table)) {
        echo "FAIL: Table '$table' does not exist!\n";
        exit(1);
    }
    echo "Table: $table\n";
    echo str_repeat("-", 40) . "\n";
    
    $columns = DB::select("DESCRIBE `$table`");
    foreach ($columns as $column) {
        printf("  - %-25s | Type: %-15s | Null: %-3s | Key: %-3s | Default: %s\n", 
            $column->Field, 
            $column->Type, 
            $column->Null, 
            $column->Key, 
            var_export($column->Default, true)
        );
    }
    
    // Check foreign keys
    $fkeys = DB::select("
        SELECT 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME 
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE 
            TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ", [$table]);
    
    if (count($fkeys) > 0) {
        echo "  Foreign Keys:\n";
        foreach ($fkeys as $fk) {
            echo "    * {$fk->COLUMN_NAME} REFERENCES {$fk->REFERENCED_TABLE_NAME}({$fk->REFERENCED_COLUMN_NAME}) (Constraint: {$fk->CONSTRAINT_NAME})\n";
        }
    }
    echo "\n";
}

echo "=== RUNTIME RELATIONSHIPS & CONSTRAINTS TEST ===\n\n";

DB::beginTransaction();

try {
    // 1. Create a User
    $user = User::factory()->create(['name' => 'Forensic Tester', 'email' => 'forensic@test.com']);
    echo "Created Test User: ID {$user->id}, Name: {$user->name}\n";

    // 2. Create a Subscription Package
    $package = SubscriptionPackage::factory()->create([
        'name' => 'Premium VIP',
        'price' => 123000,
        'duration_days' => 30,
        'features' => ['max_speed' => true, 'support_level' => 'premium']
    ]);
    echo "Created Subscription Package: ID {$package->id}, Name: {$package->name}, Features: " . json_encode($package->features) . "\n";

    // 3. Create a Payment Config
    $payConfig = PaymentConfig::factory()->create([
        'name' => 'VietQR Test',
        'prefix' => 'PAYMENT_PRFX_',
        'suffix' => '_SFX',
        'is_active' => true
    ]);
    echo "Created Payment Config: ID {$payConfig->id}, Name: {$payConfig->name}\n";

    // 4. Create User Subscription
    $subscription = UserSubscription::factory()->create([
        'user_id' => $user->id,
        'subscription_package_id' => $package->id,
        'starts_at' => now()->subMinutes(5),
        'expires_at' => now()->addDays(30),
        'status' => 'active'
    ]);
    echo "Created User Subscription: ID {$subscription->id}, Status: {$subscription->status}, Starts: {$subscription->starts_at}, Expires: {$subscription->expires_at}\n";

    // 5. Create Transaction
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'payment_config_id' => $payConfig->id,
        'amount' => 123000,
        'status' => 'success',
        'transaction_id' => 'TX_AUDIT_12345'
    ]);
    echo "Created Transaction: ID {$transaction->id}, Trans ID: {$transaction->transaction_id}, Status: {$transaction->status}, Amount: {$transaction->amount}\n";

    // 6. Test relations
    echo "\nTesting Relations from User Model:\n";
    
    // Subscriptions relation
    $userSubs = $user->subscriptions;
    echo "  - User -> subscriptions: Count = " . $userSubs->count() . "\n";
    if ($userSubs->count() > 0 && $userSubs->first()->id === $subscription->id) {
        echo "    SUCCESS: User's subscriptions relation works.\n";
    } else {
        echo "    FAIL: User's subscriptions relation did not return correct subscription.\n";
    }

    // Transactions relation
    $userTrans = $user->transactions;
    echo "  - User -> transactions: Count = " . $userTrans->count() . "\n";
    if ($userTrans->count() > 0 && $userTrans->first()->id === $transaction->id) {
        echo "    SUCCESS: User's transactions relation works.\n";
    } else {
        echo "    FAIL: User's transactions relation did not return correct transaction.\n";
    }

    // Active subscription relation
    $activeSub = $user->activeSubscription;
    if ($activeSub && $activeSub->id === $subscription->id) {
        echo "  - User -> activeSubscription: ID = {$activeSub->id}\n";
        echo "    SUCCESS: activeSubscription relation works.\n";
    } else {
        echo "    FAIL: activeSubscription relation returned null or mismatch.\n";
    }

    // 7. Test relations from UserSubscription
    echo "\nTesting Relations from UserSubscription Model:\n";
    $subUser = $subscription->user;
    echo "  - UserSubscription -> user: Name = {$subUser->name}\n";
    $subPkg = $subscription->package;
    echo "  - UserSubscription -> package: Name = {$subPkg->name}\n";
    if ($subUser->id === $user->id && $subPkg->id === $package->id) {
        echo "    SUCCESS: UserSubscription relationships work.\n";
    } else {
        echo "    FAIL: UserSubscription relationships returned mismatch.\n";
    }

    // 8. Test relations from Transaction
    echo "\nTesting Relations from Transaction Model:\n";
    $transUser = $transaction->user;
    echo "  - Transaction -> user: Name = {$transUser->name}\n";
    $transConfig = $transaction->paymentConfig;
    echo "  - Transaction -> paymentConfig: Name = {$transConfig->name}\n";
    if ($transUser->id === $user->id && $transConfig->id === $payConfig->id) {
        echo "    SUCCESS: Transaction relationships work.\n";
    } else {
        echo "    FAIL: Transaction relationships returned mismatch.\n";
    }

    // 9. Test Foreign Key constraints deletion restrictions
    echo "\nTesting Delete Constraints:\n";
    
    // Deleting package with active subscription should fail
    try {
        echo "  - Trying to delete SubscriptionPackage (ID: {$package->id})...\n";
        $package->delete();
        echo "    FAIL: Package deleted without restriction!\n";
    } catch (\Illuminate\Database\QueryException $e) {
        echo "    SUCCESS: Prevented package deletion. Error: " . $e->getMessage() . "\n";
    }

    // Deleting payment config with transaction should fail
    try {
        echo "  - Trying to delete PaymentConfig (ID: {$payConfig->id})...\n";
        $payConfig->delete();
        echo "    FAIL: PaymentConfig deleted without restriction!\n";
    } catch (\Illuminate\Database\QueryException $e) {
        echo "    SUCCESS: Prevented PaymentConfig deletion. Error: " . $e->getMessage() . "\n";
    }

    echo "\nALL RUNTIME VERIFICATIONS COMPLETED SUCCESSFULLY!\n";

} catch (\Exception $e) {
    echo "EXCEPTION THROWN DURING VERIFICATION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    DB::rollBack();
    echo "\nDatabase transaction rolled back cleanly.\n";
}
