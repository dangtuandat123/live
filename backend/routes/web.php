<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Models\LiveSession;
use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Landing Page (Blade SSR - SEO tối ưu)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('landing');
})->name('home');

/*
|--------------------------------------------------------------------------
| App Area (React Inertia CSR - sau khi đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Products (CRUD)
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Lives (Sessions)
    Route::get('/lives', [LiveSessionController::class, 'index'])->name('lives.index');
    Route::get('/lives/create', [LiveSessionController::class, 'create'])->name('lives.create');
    Route::post('/lives', [LiveSessionController::class, 'store'])->name('lives.store');
    Route::get('/lives/{liveSession}', [LiveSessionController::class, 'show'])->name('lives.show');
    Route::post('/lives/{liveSession}/stop', [LiveSessionController::class, 'stop'])->name('lives.stop');
    Route::post('/lives/{liveSession}/fetch-events', [LiveSessionController::class, 'fetchEvents'])->name('lives.fetch-events');
    Route::delete('/lives/{liveSession}', [LiveSessionController::class, 'destroy'])->name('lives.destroy');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/ai', [SettingsController::class, 'updateSettings'])->name('settings.update-ai');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.update-profile');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin Area (React Inertia CSR - chỉ admin truy cập)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        $totalUsers = User::count();
        $totalSessions = LiveSession::count();

        // Hoạt động 7 ngày qua
        $activeUsers = LiveSession::where('created_at', '>=', now()->subDays(7))
            ->distinct('user_id')
            ->count('user_id');

        // Doanh thu ước tính (M)
        $revenueVal = round(($totalUsers * 299000) / 1000000, 1);
        $estimatedRevenue = $revenueVal.'M';

        // Biểu đồ 5 tháng gần đây
        $revenueData = [];
        for ($i = 4; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthLabel = 'T'.$monthDate->format('m');

            // Tính số lượng user đăng ký trước hoặc bằng ngày cuối tháng này
            $usersCount = User::where('created_at', '<=', $monthDate->endOfMonth())->count();

            $revenueData[] = [
                'month' => $monthLabel,
                'revenue' => $usersCount * 299000,
                'users' => $usersCount,
            ];
        }

        // Người dùng gần đây
        $recentUsers = User::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($u) {
                $sessionsCount = LiveSession::where('user_id', $u->id)->count();
                // Phân bổ plan giả định dựa trên ID để đa dạng giao diện
                $plan = 'Free';
                if ($u->id % 3 === 0) {
                    $plan = 'Pro';
                } elseif ($u->id % 3 === 1) {
                    $plan = 'Business';
                }

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'plan' => $plan,
                    'date' => $u->created_at?->format('d/m/Y') ?? '',
                    'sessions' => $sessionsCount,
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                [
                    'title' => 'Tổng người dùng',
                    'value' => number_format($totalUsers),
                    'change' => '+'.User::where('created_at', '>=', now()->startOfMonth())->count().' tháng này',
                    'trend' => 'up',
                ],
                [
                    'title' => 'Tổng phiên Live',
                    'value' => number_format($totalSessions),
                    'change' => '+'.LiveSession::where('created_at', '>=', now()->startOfMonth())->count().' tháng này',
                    'trend' => 'up',
                ],
                [
                    'title' => 'Doanh thu ước tính',
                    'value' => $estimatedRevenue,
                    'change' => '+15% so với tháng trước',
                    'trend' => 'up',
                ],
                [
                    'title' => 'User hoạt động (7d)',
                    'value' => number_format($activeUsers),
                    'change' => 'Dựa trên phiên live gần đây',
                    'trend' => 'up',
                ],
            ],
            'revenueData' => $revenueData,
            'recentUsers' => $recentUsers,
        ]);
    })->name('admin.dashboard');

    Route::get('/users', function () {
        $users = User::orderByDesc('created_at')->get();

        return Inertia::render('Admin/Users/Index', ['users' => $users]);
    })->name('admin.users.index');

    Route::put('/users/{user}/role', function (Request $request, User $user) {
        $request->validate(['role' => ['required', 'in:user,admin']]);
        $user->update(['role' => $request->role]);

        return back()->with('success', 'Đã cập nhật quyền thành công.');
    })->name('admin.users.update-role');

    Route::get('/settings', function () {
        $defaultSettings = [
            'app_name' => 'LiveStream AI',
            'app_url' => 'http://localhost:8000',
            'allow_registration' => true,
            'require_email_verification' => true,
            'enable_two_factor' => false,
            'max_free_sessions' => 5,
            'notify_new_user' => true,
            'weekly_report' => true,
        ];

        $settingsPath = 'system_settings.json';
        if (! Storage::disk('local')->exists($settingsPath)) {
            Storage::disk('local')->put($settingsPath, json_encode($defaultSettings, JSON_PRETTY_PRINT));
            $settings = $defaultSettings;
        } else {
            $settings = json_decode(Storage::disk('local')->get($settingsPath), true) ?? $defaultSettings;
        }

        return Inertia::render('Admin/Settings/Index', ['settings' => $settings]);
    })->name('admin.settings.index');

    Route::put('/settings', function (Request $request) {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'allow_registration' => ['required', 'boolean'],
            'require_email_verification' => ['required', 'boolean'],
            'enable_two_factor' => ['required', 'boolean'],
            'max_free_sessions' => ['required', 'integer', 'min:1', 'max:999'],
            'notify_new_user' => ['required', 'boolean'],
            'weekly_report' => ['required', 'boolean'],
        ]);

        Storage::disk('local')->put('system_settings.json', json_encode($validated, JSON_PRETTY_PRINT));

        return back()->with('success', 'Đã lưu cấu hình hệ thống thành công.');
    })->name('admin.settings.update');

    // Gói dịch vụ (CRUD packages)
    Route::get('/packages', function () {
        $packages = SubscriptionPackage::orderBy('price')->get();

        return Inertia::render('Admin/Packages/Index', ['packages' => $packages]);
    })->name('admin.packages.index');

    Route::post('/packages', function (Request $request) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
        ]);

        SubscriptionPackage::create($validated);

        return back()->with('success', 'Đã tạo gói dịch vụ mới thành công.');
    })->name('admin.packages.store');

    Route::put('/packages/{package}', function (Request $request, SubscriptionPackage $package) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
        ]);

        $package->update($validated);

        return back()->with('success', 'Đã cập nhật gói dịch vụ thành công.');
    })->name('admin.packages.update');

    Route::delete('/packages/{package}', function (SubscriptionPackage $package) {
        $hasAssociations = UserSubscription::where('subscription_package_id', $package->id)->exists()
            || Transaction::where('subscription_package_id', $package->id)->exists();
        if ($hasAssociations) {
            return back()->withErrors(['error' => 'Không thể xóa gói dịch vụ đã có lịch sử đăng ký hoặc giao dịch.']);
        }
        try {
            $package->delete();
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Lỗi khi xóa gói dịch vụ: '.$e->getMessage()]);
        }

        return back()->with('success', 'Đã xóa gói dịch vụ thành công.');
    })->name('admin.packages.destroy');

    // Cấu hình thanh toán & webhook
    Route::get('/payments', function () {
        $config = PaymentConfig::where('is_active', true)->first()
            ?? PaymentConfig::first()
            ?? new PaymentConfig;

        return Inertia::render('Admin/Payments/Index', ['config' => $config]);
    })->name('admin.payments.index');

    Route::put('/payments', function (Request $request) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'method' => ['required', 'in:POST,GET,PUT'],
            'params_template' => ['nullable', 'array'],
            'headers_template' => ['nullable', 'array'],
        ]);

        $config = PaymentConfig::where('is_active', true)->first() ?? PaymentConfig::first();
        if ($config) {
            $config->update($validated);
        } else {
            $validated['is_active'] = true;
            PaymentConfig::create($validated);
        }

        return back()->with('success', 'Đã cập nhật cấu hình thanh toán thành công.');
    })->name('admin.payments.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Giao diện mua gói đăng ký và thanh toán của User
    Route::get('/subscription', function () {
        $packages = SubscriptionPackage::orderBy('price')->get();
        $user = auth()->user();
        $activeSub = $user->resolveActiveSubscription();

        $transactions = $user->transactions()
            ->with('package')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'transaction_id' => $t->transaction_id,
                    'package_name' => $t->package?->name ?? 'Free',
                    'amount' => $t->amount,
                    'status' => $t->status,
                    'created_at' => $t->created_at?->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Subscription/Index', [
            'packages' => $packages,
            'activeSubscription' => $activeSub ? [
                'package_id' => $activeSub->subscription_package_id,
                'expires_at' => $activeSub->expires_at?->format('d/m/Y H:i') ?? 'Vĩnh viễn',
                'package_name' => $activeSub->package?->name ?? 'Free',
                'used_ai_credits' => $activeSub->used_ai_credits ?? 0,
                'features' => $user->getSubscriptionFeatures(),
            ] : null,
            'transactions' => $transactions,
        ]);
    })->name('subscription.index');
});

require __DIR__.'/auth.php';
