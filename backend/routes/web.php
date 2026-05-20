<?php

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
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Products
    Route::get('/products', function () {
        return Inertia::render('Products/Index');
    })->name('products.index');

    // Lives
    Route::get('/lives', function () {
        return Inertia::render('Lives/Index');
    })->name('lives.index');

    Route::get('/lives/create', function () {
        return Inertia::render('Lives/Setup');
    })->name('lives.create');

    Route::get('/lives/{id}', function (string $id) {
        return Inertia::render('Lives/Show', ['id' => $id]);
    })->name('lives.show');

    // Reports
    Route::get('/reports', function () {
        return Inertia::render('Reports/Index');
    })->name('reports.index');

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
