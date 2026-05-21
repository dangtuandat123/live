<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * List all packages.
     *
     * GET /api/subscription/packages
     */
    public function index()
    {
        $packages = SubscriptionPackage::all();

        return response()->json($packages);
    }

    /**
     * Get the authenticated user's active subscription status.
     *
     * GET /api/subscription/status
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $activeSub = $user->activeSubscription;

        if (! $activeSub) {
            return response()->json([
                'active' => false,
                'package_id' => null,
                'expires_at' => null,
            ]);
        }

        return response()->json([
            'active' => true,
            'package_id' => $activeSub->subscription_package_id,
            'expires_at' => $activeSub->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Initiate subscription checkout.
     *
     * POST /api/subscription/checkout
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
        ]);

        $package = SubscriptionPackage::findOrFail($validated['package_id']);
        $user = $request->user();

        $paymentConfig = PaymentConfig::where('is_active', true)->first();
        if (! $paymentConfig) {
            return response()->json([
                'error' => 'Service Unavailable',
                'message' => 'No active payment configuration found.',
            ], 503);
        }

        if ($package->price === 0) {
            if (UserSubscription::where('user_id', $user->id)->where('subscription_package_id', $package->id)->exists()) {
                return response()->json([
                    'error' => 'Bad Request',
                    'message' => 'You have already subscribed to this free package.'
                ], 400);
            }

            if ($user->activeSubscription) {
                return response()->json([
                    'error' => 'Bad Request',
                    'message' => 'You already have an active subscription.'
                ], 400);
            }
            // Free package: activate instantly
            DB::beginTransaction();
            try {
                $transactionId = ($paymentConfig->prefix ?? 'TX_').strtoupper(Str::random(10));

                $transaction = Transaction::create([
                    'transaction_id' => $transactionId,
                    'user_id' => $user->id,
                    'amount' => 0,
                    'payment_config_id' => $paymentConfig->id,
                    'subscription_package_id' => $package->id,
                    'vietqr_url' => null,
                    'status' => 'success',
                ]);

                // Upgrade / activate subscription logic
                $activeSub = $user->activeSubscription;
                if ($activeSub && $activeSub->subscription_package_id === $package->id) {
                    $activeSub->update([
                        'expires_at' => $activeSub->expires_at->addDays($package->duration_days),
                    ]);
                } else {
                    if ($activeSub) {
                        $activeSub->update(['status' => 'inactive']);
                    }
                    UserSubscription::create([
                        'user_id' => $user->id,
                        'subscription_package_id' => $package->id,
                        'starts_at' => now(),
                        'expires_at' => now()->addDays($package->duration_days),
                        'status' => 'active',
                    ]);
                }

                DB::commit();

                return response()->json([
                    'transaction_id' => $transaction->transaction_id,
                    'vietqr_url' => null,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Internal Server Error',
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

        // Paid package: generate transaction and VietQR
        $transactionId = ($paymentConfig->prefix ?? 'TX_').strtoupper(Str::random(10));

        $vietQrTemplate = 'https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}';

        $prefixVal = rawurlencode($paymentConfig->prefix ?? '');
        $userIdVal = rawurlencode((string) $user->id);
        $suffixVal = rawurlencode($paymentConfig->suffix ?? '');
        $amountVal = rawurlencode((string) $package->price);

        $vietQrUrl = str_replace(
            ['{Prefix}', '{userId}', '{Suffix}', '{amount}'],
            [$prefixVal, $userIdVal, $suffixVal, $amountVal],
            $vietQrTemplate
        );

        $transaction = Transaction::create([
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'amount' => $package->price,
            'payment_config_id' => $paymentConfig->id,
            'subscription_package_id' => $package->id,
            'vietqr_url' => $vietQrUrl,
            'status' => 'pending',
        ]);

        return response()->json([
            'transaction_id' => $transaction->transaction_id,
            'vietqr_url' => $transaction->vietqr_url,
        ]);
    }
}
