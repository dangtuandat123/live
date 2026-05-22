<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

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
        if (! $paymentConfig || empty($paymentConfig->account_no) || empty($paymentConfig->bank_name)) {
            return response()->json([
                'error' => 'Service Unavailable',
                'message' => 'Cấu hình thanh toán chưa đầy đủ. Vui lòng liên hệ Admin.',
            ], 503);
        }

        if ($package->price === 0) {
            DB::beginTransaction();
            try {
                // Lock the user record to prevent concurrent checkout race conditions
                User::where('id', $user->id)->lockForUpdate()->first();

                // Lock UserSubscriptions for the user
                $existingFreeSub = UserSubscription::where('user_id', $user->id)
                    ->where('subscription_package_id', $package->id)
                    ->lockForUpdate()
                    ->exists();

                if ($existingFreeSub) {
                    DB::rollBack();

                    return response()->json([
                        'error' => 'Bad Request',
                        'message' => 'You have already subscribed to this free package.',
                    ], 400);
                }

                $activeSub = $user->activeSubscription()->lockForUpdate()->first();
                if ($activeSub) {
                    DB::rollBack();

                    return response()->json([
                        'error' => 'Bad Request',
                        'message' => 'You already have an active subscription.',
                    ], 400);
                }

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

                UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_package_id' => $package->id,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($package->duration_days),
                    'status' => 'active',
                ]);

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

        $vietQrTemplate = $paymentConfig->qr_template ?? 'https://api.vietqr.io/image/{bank_id}-{account_no}-rdXzPHV.jpg?accountName={account_name}&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}';

        $bankIdVal = rawurlencode($paymentConfig->bank_id ?? '970416');
        $accountNoVal = rawurlencode($paymentConfig->account_no);
        $accountNameVal = rawurlencode($paymentConfig->account_name ?? '');
        $prefixVal = rawurlencode($paymentConfig->prefix ?? '');
        $userIdVal = rawurlencode((string) $user->id);
        $suffixVal = rawurlencode($paymentConfig->suffix ?? '');
        $amountVal = rawurlencode((string) $package->price);

        $vietQrUrl = str_replace(
            ['{bank_id}', '{account_no}', '{account_name}', '{Prefix}', '{userId}', '{Suffix}', '{amount}'],
            [$bankIdVal, $accountNoVal, $accountNameVal, $prefixVal, $userIdVal, $suffixVal, $amountVal],
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
            'beneficiary_bank' => $paymentConfig->bank_name,
            'beneficiary_account' => $paymentConfig->account_no,
            'beneficiary_name' => $paymentConfig->account_name,
        ]);
    }

    /**
     * Admin: Display list of subscription packages.
     */
    public function packagesIndex()
    {
        $packages = SubscriptionPackage::orderBy('price')->get();

        return Inertia::render('Admin/Packages/Index', ['packages' => $packages]);
    }

    /**
     * Admin: Store a new subscription package.
     */
    public function storePackage(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.limit_streams' => ['nullable', 'integer', 'min:-1'],
            'features.max_duration_hours' => ['nullable', 'integer', 'min:-1'],
            'features.ai_credits' => ['nullable', 'integer', 'min:-1'],
            'features.audio_analysis' => ['nullable', 'boolean'],
            'features.export_leads' => ['nullable', 'boolean'],
        ]);

        SubscriptionPackage::create($validated);

        return back()->with('success', 'Đã tạo gói dịch vụ mới thành công.');
    }

    /**
     * Admin: Update an existing subscription package.
     */
    public function updatePackage(Request $request, SubscriptionPackage $package)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.limit_streams' => ['nullable', 'integer', 'min:-1'],
            'features.max_duration_hours' => ['nullable', 'integer', 'min:-1'],
            'features.ai_credits' => ['nullable', 'integer', 'min:-1'],
            'features.audio_analysis' => ['nullable', 'boolean'],
            'features.export_leads' => ['nullable', 'boolean'],
        ]);

        $package->update($validated);

        return back()->with('success', 'Đã cập nhật gói dịch vụ thành công.');
    }

    /**
     * Admin: Delete a subscription package.
     */
    public function destroyPackage(SubscriptionPackage $package)
    {
        $hasAssociations = UserSubscription::where('subscription_package_id', $package->id)->exists()
            || Transaction::where('subscription_package_id', $package->id)->exists();
        if ($hasAssociations) {
            return back()->withErrors(['error' => 'Không thể xóa gói dịch vụ đã có lịch sử đăng ký hoặc giao dịch.']);
        }
        try {
            $package->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Lỗi khi xóa gói dịch vụ: '.$e->getMessage()]);
        }

        return back()->with('success', 'Đã xóa gói dịch vụ thành công.');
    }
}

