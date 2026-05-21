<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
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

    // Reports
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

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
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');

    Route::get('/users', function () {
        $users = \App\Models\User::orderByDesc('created_at')->get();
        return Inertia::render('Admin/Users/Index', ['users' => $users]);
    })->name('admin.users.index');

    Route::put('/users/{user}/role', function (\Illuminate\Http\Request $request, \App\Models\User $user) {
        $request->validate(['role' => ['required', 'in:user,admin']]);
        $user->update(['role' => $request->role]);
        return back()->with('success', 'Đã cập nhật quyền thành công.');
    })->name('admin.users.update-role');

    Route::get('/settings', function () {
        return Inertia::render('Admin/Settings/Index');
    })->name('admin.settings.index');
});

require __DIR__.'/auth.php';
