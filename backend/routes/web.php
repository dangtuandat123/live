<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Models\LiveSession;
use App\Models\PaymentConfig;
use App\Models\SubscriptionPackage;
use App\Models\Transaction;
use App\Models\User;
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
    Route::post('/lives/{liveSession}/refresh-insights', [LiveSessionController::class, 'refreshInsights'])->name('lives.refresh-insights');
    Route::delete('/lives/{liveSession}', [LiveSessionController::class, 'destroy'])->name('lives.destroy');
    Route::put('/api/live-events/{liveEvent}', [LiveSessionController::class, 'updateEvent'])->name('live-events.update');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/ai', [SettingsController::class, 'updateSettings'])->name('settings.update-ai');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.update-profile');
    Route::post('/settings/tiktok/connect', [SettingsController::class, 'connectTikTok'])->name('settings.tiktok.connect');
    Route::post('/settings/tiktok/disconnect', [SettingsController::class, 'disconnectTikTok'])->name('settings.tiktok.disconnect');
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

        // Tổng doanh thu
        $revenueVal = Transaction::where('status', 'success')->sum('amount');
        $totalRevenueVal = $revenueVal;

        // Dynamic KPI growth calculation
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // Users Growth
        $usersThisMonth = User::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->count();
        $usersLastMonth = User::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        if ($usersLastMonth > 0) {
            $usersDiffPercent = (($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100;
            $usersTrend = $usersDiffPercent >= 0 ? 'up' : 'down';
            $usersChange = ($usersDiffPercent >= 0 ? '+' : '').number_format($usersDiffPercent, 1).'% so với tháng trước';
        } else {
            $usersTrend = 'up';
            $usersChange = '+'.$usersThisMonth.' tháng này';
        }

        // Sessions Growth
        $sessionsThisMonth = LiveSession::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->count();
        $sessionsLastMonth = LiveSession::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        if ($sessionsLastMonth > 0) {
            $sessionsDiffPercent = (($sessionsThisMonth - $sessionsLastMonth) / $sessionsLastMonth) * 100;
            $sessionsTrend = $sessionsDiffPercent >= 0 ? 'up' : 'down';
            $sessionsChange = ($sessionsDiffPercent >= 0 ? '+' : '').number_format($sessionsDiffPercent, 1).'% so với tháng trước';
        } else {
            $sessionsTrend = 'up';
            $sessionsChange = '+'.$sessionsThisMonth.' tháng này';
        }

        // Revenue Growth
        $currentMonthRevenue = Transaction::where('status', 'success')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');
        $lastMonthRevenue = Transaction::where('status', 'success')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');
        if ($lastMonthRevenue > 0) {
            $revenueDiffPercent = (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
            $revenueTrend = $revenueDiffPercent >= 0 ? 'up' : 'down';
            $revenueChange = ($revenueDiffPercent >= 0 ? '+' : '').number_format($revenueDiffPercent, 1).'% so với tháng trước';
        } else {
            $revenueTrend = 'up';
            $revenueChange = '+'.number_format($currentMonthRevenue).'đ tháng này';
        }

        // Active Users Growth
        $activeUsersLast7Days = $activeUsers;
        $activeUsersPrev7Days = LiveSession::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)->subSecond()])
            ->distinct('user_id')
            ->count('user_id');
        if ($activeUsersPrev7Days > 0) {
            $activeDiffPercent = (($activeUsersLast7Days - $activeUsersPrev7Days) / $activeUsersPrev7Days) * 100;
            $activeTrend = $activeDiffPercent >= 0 ? 'up' : 'down';
            $activeChange = ($activeDiffPercent >= 0 ? '+' : '').number_format($activeDiffPercent, 1).'% so với 7 ngày trước';
        } else {
            $activeTrend = 'up';
            $activeChange = 'Dựa trên phiên live gần đây';
        }

        // Biểu đồ 5 tháng gần đây
        $revenueData = [];
        for ($i = 4; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthLabel = 'T'.$monthDate->format('m');

            $startOfMonth = $monthDate->copy()->startOfMonth();
            $endOfMonth = $monthDate->copy()->endOfMonth();

            // Tính số lượng user đăng ký trước hoặc bằng ngày cuối tháng này
            $usersCount = User::where('created_at', '<=', $endOfMonth)->count();

            $monthlyRevenue = Transaction::where('status', 'success')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $revenueData[] = [
                'month' => $monthLabel,
                'revenue' => $monthlyRevenue,
                'users' => $usersCount,
            ];
        }

        // Người dùng gần đây
        $recentUsers = User::with(['subscriptions', 'activeSubscription.package'])->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($u) {
                $sessionsCount = LiveSession::where('user_id', $u->id)->count();
                $activeSub = $u->resolveActiveSubscription();
                $plan = $activeSub && $activeSub->package ? $activeSub->package->name : 'Free';

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
                    'change' => $usersChange,
                    'trend' => $usersTrend,
                ],
                [
                    'title' => 'Tổng phiên Live',
                    'value' => number_format($totalSessions),
                    'change' => $sessionsChange,
                    'trend' => $sessionsTrend,
                ],
                [
                    'title' => 'Tổng doanh thu',
                    'value' => number_format($totalRevenueVal).'đ',
                    'change' => $revenueChange,
                    'trend' => $revenueTrend,
                ],
                [
                    'title' => 'User hoạt động (7d)',
                    'value' => number_format($activeUsers),
                    'change' => $activeChange,
                    'trend' => $activeTrend,
                ],
            ],
            'revenueData' => $revenueData,
            'recentUsers' => $recentUsers,
        ]);
    })->name('admin.dashboard');

    Route::get('/users', function () {
        $users = User::with(['subscriptions', 'activeSubscription.package'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($u) {
                $activeSub = $u->resolveActiveSubscription();
                $u->plan_name = $activeSub && $activeSub->package ? $activeSub->package->name : 'Free';

                return $u;
            });

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
    Route::get('/packages', [SubscriptionController::class, 'packagesIndex'])->name('admin.packages.index');
    Route::post('/packages', [SubscriptionController::class, 'storePackage'])->name('admin.packages.store');
    Route::put('/packages/{package}', [SubscriptionController::class, 'updatePackage'])->name('admin.packages.update');
    Route::delete('/packages/{package}', [SubscriptionController::class, 'destroyPackage'])->name('admin.packages.destroy');

    // Cấu hình thanh toán & webhook
    Route::get('/payments', function () {
        $config = PaymentConfig::where('is_active', true)->first()
            ?? PaymentConfig::first()
            ?? new PaymentConfig;

        $totalRevenue = Transaction::where('status', 'success')->sum('amount');

        return Inertia::render('Admin/Payments/Index', [
            'config' => $config,
            'total_revenue' => $totalRevenue,
        ]);
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
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_id' => ['nullable', 'string', 'max:50'],
            'account_no' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'qr_template' => ['nullable', 'string', 'max:1000'],
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

    Route::get('/api/subscription/status', [SubscriptionController::class, 'status'])->name('subscription.status');
    Route::post('/api/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
});

require __DIR__.'/auth.php';
