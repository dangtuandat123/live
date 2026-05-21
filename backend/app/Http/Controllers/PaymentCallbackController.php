<?php

namespace App\Http\Controllers;

use App\Jobs\SendOutboundPaymentWebhookJob;
use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentCallbackController extends Controller
{
    /**
     * Handle the public payment callback from banking system / VietQR.
     *
     * POST /api/payments/callback
     */
    public function handleCallback(Request $request)
    {
        $validated = $request->validate([
            'id_user' => ['required', 'integer', 'exists:users,id'],
            'sotien' => ['required', 'integer', 'min:0'],
        ]);

        $userId = $validated['id_user'];
        $amount = $validated['sotien'];

        // Find active payment configuration
        $activePaymentConfig = PaymentConfig::where('is_active', true)->first();
        if (! $activePaymentConfig) {
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => 'No active payment config found.',
            ], 500);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::where('user_id', $userId)
                ->where('amount', $amount)
                ->where('status', 'pending')
                ->latest()
                ->lockForUpdate()
                ->first();

            $package = null;
            if ($transaction) {
                $package = SubscriptionPackage::find($transaction->subscription_package_id);
            }
            if (!$package) {
                $package = SubscriptionPackage::where('price', $amount)->first();
            }

            if (!$package) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Unprocessable Content',
                    'message' => "No subscription package found for price {$amount}.",
                ], 422);
            }

            if (!$transaction) {
                $recentSuccess = Transaction::where('user_id', $userId)
                    ->where('amount', $amount)
                    ->where('status', 'success')
                    ->where('updated_at', '>=', now()->subMinutes(5))
                    ->latest()
                    ->lockForUpdate()
                    ->first();

                if ($recentSuccess) {
                    DB::rollBack();
                    return response()->json([
                        'success' => true,
                        'message' => 'Subscription upgraded successfully (duplicate callback ignored)',
                    ]);
                }
            }

            if ($transaction) {
                $transaction->update([
                    'status' => 'success',
                    'subscription_package_id' => $package->id,
                ]);
            } else {
                // Audit success transaction
                $transactionId = ($activePaymentConfig->prefix ?? 'TX_').strtoupper(Str::random(10));
                $transaction = Transaction::create([
                    'transaction_id' => $transactionId,
                    'user_id' => $userId,
                    'amount' => $amount,
                    'payment_config_id' => $activePaymentConfig->id,
                    'subscription_package_id' => $package->id,
                    'vietqr_url' => null,
                    'status' => 'success',
                ]);
            }

            // Upgrade User Subscription
            $user = User::findOrFail($userId);
            $activeSub = $user->activeSubscription;

            if ($activeSub && $activeSub->subscription_package_id === $package->id) {
                // Same package: extend expires_at
                $currentExpiry = $activeSub->expires_at ?? now();
                // Ensure currentExpiry is a Carbon instance (casts on model makes it Carbon, but if null we use now())
                $activeSub->update([
                    'expires_at' => $currentExpiry->addDays($package->duration_days),
                ]);
            } else {
                // Different package or no active sub: deactivate old one
                if ($activeSub) {
                    $activeSub->update(['status' => 'inactive']);
                }

                // Create fresh one starting now
                UserSubscription::create([
                    'user_id' => $userId,
                    'subscription_package_id' => $package->id,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($package->duration_days),
                    'status' => 'active',
                ]);
            }

            DB::commit();

            if (! empty($activePaymentConfig->webhook_url)) {
                try {
                    SendOutboundPaymentWebhookJob::dispatch($transaction->id);
                } catch (\Exception $webhookEx) {
                    \Illuminate\Support\Facades\Log::error('Outbound webhook failed: ' . $webhookEx->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription upgraded successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
