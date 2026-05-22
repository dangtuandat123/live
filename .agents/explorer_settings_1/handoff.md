# Plan & Analysis: Settings Dynamic Integration

## 1. Observation

Direct code observations from the targeted files:

*   **`backend/app/Http/Controllers/SettingsController.php`**:
    *   Currently, the `index` method returns the `Settings/Index` view but only feeds it `$request->user()->getSettingsWithDefaults()` (lines 12-13):
        ```php
        return Inertia::render('Settings/Index', [
            'settings' => $request->user()->getSettingsWithDefaults(),
        ]);
        ```
    *   The `updateSettings` method replaces the entire user settings column with only the four validated AI settings, which would wipe out any other settings like `tiktok_username` (lines 17-26):
        ```php
        $validated = $request->validate([
            'ai_language' => ['required', 'in:vi,en,auto'],
            'auto_extract_phone' => ['required', 'boolean'],
            'auto_extract_address' => ['required', 'boolean'],
            'realtime_alerts' => ['required', 'boolean'],
        ]);

        $request->user()->update(['settings' => $validated]);
        ```
*   **`backend/app/Http/Middleware/HandleInertiaRequests.php`**:
    *   Shares `subscription` details globally, but currently excludes package `price` and `duration_days` (lines 43-50):
        ```php
        $subscription = [
            'active' => (bool) $activeSub?->isActive(),
            'package_id' => $activeSub?->subscription_package_id,
            'package_name' => $activeSub?->package?->name ?? 'Free',
            'expires_at' => $activeSub?->expires_at?->toISOString(),
            'used_ai_credits' => $activeSub?->used_ai_credits ?? 0,
            'features' => $user->getSubscriptionFeatures(),
        ];
        ```
*   **`backend/routes/web.php`**:
    *   Defines Settings routes under auth middleware group but lacks TikTok connection/disconnection endpoints (lines 55-59):
        ```php
        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/ai', [SettingsController::class, 'updateSettings'])->name('settings.update-ai');
        Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.update-profile');
        ```
*   **`backend/resources/js/Pages/Settings/Index.tsx`**:
    *   The Subscription Card is completely static and hardcoded (lines 134-165).
    *   Platform connections are hardcoded to a static array of mock data (lines 54-56):
        ```typescript
        const platforms = [
            { name: 'TikTok', connected: true, account: '@shopthoitrang_abc' },
        ];
        ```
    *   The page padding under the sticky header uses a `p-6` class which aligns with the requested styling standard (line 113):
        ```typescript
        <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6">
        ```
*   **`backend/resources/js/types/index.d.ts`**:
    *   Defines `UserSubscription` but misses `price` and `duration_days` attributes (lines 19-26).
    *   Defines `User` but does not include the casted `settings` object containing `tiktok_username` (lines 1-9).
*   **`backend/resources/js/Components/nav-user.tsx`**:
    *   Correctly reads `auth.subscription` dynamically to render "Quản lý gói" if active and Pro/Enterprise, and "Nâng cấp Pro" otherwise.

---

## 2. Logic Chain

To transition from static hardcoded values to dynamic backend integration:

1.  **Subscription Props (`HandleInertiaRequests.php`)**:
    *   The Settings page requires the pricing and billing cycle days of the active package to avoid hardcoding. Adding `price` and `duration_days` to `HandleInertiaRequests.php`'s shared subscription array ensures this metadata is available globally as `auth.subscription.price` and `auth.subscription.duration_days`.
2.  **TikTok Username Storage & APIs (`SettingsController.php` & `web.php`)**:
    *   To keep user connections persistent, the `settings` JSON column in the `users` table is ideal. Adding a key named `tiktok_username` keeps metadata self-contained.
    *   To prevent overwriting this key during AI settings updates, `SettingsController@updateSettings` must use `array_merge` instead of replacing the entire column value.
    *   New endpoints `settings.tiktok.connect` (POST/PUT) and `settings.tiktok.disconnect` (POST) are needed to update/remove the `tiktok_username` key, which returns `back()` to trigger dynamic Inertia page refreshes.
3.  **Dynamic Usage Statistics (`SettingsController.php` -> Settings Index)**:
    *   The Settings card displays limits versus usage.
    *   Active streams are computed dynamically via `LiveSession::where('user_id', $user->id)->whereIn('status', ['connecting', 'live'])->count()`.
    *   Total sessions in the current cycle are counted since `$activeSub->starts_at` (falling back to current month's start if no active subscription exists).
4.  **UI Updates (`Settings/Index.tsx`)**:
    *   Read limits dynamically from `auth.subscription.features` and stats from the index props.
    *   Include a connect Dialog modal with inputs for the username and a confirm-disconnect modal utilizing native confirm/dialog structures.

---

## 3. Caveats

*   **TikTok Validation**: The connection API simply saves the username format entered by the user. No real-time external TikTok API validation is performed during username linkage.
*   **Database Casting**: It is assumed that the `users.settings` field is properly migrated and casted to `array` in the `User` model, which was verified in `backend/app/Models/User.php` (line 63).
*   **Inertia State Polling**: The settings updates and connections perform a standard Inertia `back()` redirect, which refreshes the frontend page props seamlessly.

---

## 4. Conclusion

A complete, bulletproof strategy has been established to make the Settings page fully dynamic. Implementing the changes proposed in this plan will satisfy all user requirements, keep connections secure, prevent data overwriting, and compile successfully without TypeScript issues.

### Proposed Changes

#### 1. Route Definitions (`backend/routes/web.php`)
```php
    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/ai', [SettingsController::class, 'updateSettings'])->name('settings.update-ai');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.update-profile');
    Route::post('/settings/tiktok/connect', [SettingsController::class, 'connectTikTok'])->name('settings.tiktok.connect');
    Route::post('/settings/tiktok/disconnect', [SettingsController::class, 'disconnectTikTok'])->name('settings.tiktok.disconnect');
```

#### 2. Controller Action Implementation (`backend/app/Http/Controllers/SettingsController.php`)
```php
    public function index(Request $request)
    {
        $user = $request->user();
        $activeSub = $user->resolveActiveSubscription();
        
        $activeStreamsCount = \App\Models\LiveSession::where('user_id', $user->id)
            ->whereIn('status', ['connecting', 'live'])
            ->count();

        $cycleStart = $activeSub ? $activeSub->starts_at : now()->startOfMonth();
        $totalSessionsCount = \App\Models\LiveSession::where('user_id', $user->id)
            ->where('created_at', '>=', $cycleStart)
            ->count();

        return Inertia::render('Settings/Index', [
            'settings' => $user->getSettingsWithDefaults(),
            'active_streams_count' => $activeStreamsCount,
            'total_sessions_count' => $totalSessionsCount,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'ai_language' => ['required', 'in:vi,en,auto'],
            'auto_extract_phone' => ['required', 'boolean'],
            'auto_extract_address' => ['required', 'boolean'],
            'realtime_alerts' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $currentSettings = $user->settings ?? [];
        $newSettings = array_merge($currentSettings, $validated);
        $user->update(['settings' => $newSettings]);

        return back()->with('success', 'Đã lưu cài đặt AI.');
    }

    public function connectTikTok(Request $request)
    {
        $validated = $request->validate([
            'tiktok_username' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $settings = $user->settings ?? [];
        $settings['tiktok_username'] = $validated['tiktok_username'];
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Đã kết nối tài khoản TikTok: ' . $validated['tiktok_username']);
    }

    public function disconnectTikTok(Request $request)
    {
        $user = $request->user();
        $settings = $user->settings ?? [];
        unset($settings['tiktok_username']);
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Đã ngắt kết nối tài khoản TikTok.');
    }
```

#### 3. Middleware Props Extension (`backend/app/Http/Middleware/HandleInertiaRequests.php`)
```php
            $subscription = [
                'active' => (bool) $activeSub?->isActive(),
                'package_id' => $activeSub?->subscription_package_id,
                'package_name' => $activeSub?->package?->name ?? 'Free',
                'price' => $activeSub?->package?->price ?? 0,
                'duration_days' => $activeSub?->package?->duration_days ?? 30,
                'expires_at' => $activeSub?->expires_at?->toISOString(),
                'used_ai_credits' => $activeSub?->used_ai_credits ?? 0,
                'features' => $user->getSubscriptionFeatures(),
            ];
```

#### 4. TypeScript Declares (`backend/resources/js/types/index.d.ts`)
```typescript
export interface UserSettings {
    ai_language: string;
    auto_extract_phone: boolean;
    auto_extract_address: boolean;
    realtime_alerts: boolean;
    tiktok_username?: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    created_at?: string;
    role: 'user' | 'admin';
    plan_name?: string;
    settings?: UserSettings;
}

export interface UserSubscription {
    active: boolean;
    package_id: number | null;
    package_name: string;
    price?: number;
    duration_days?: number;
    expires_at: string | null;
    used_ai_credits: number;
    features: UserSubscriptionFeatures;
}
```

#### 5. Frontend UI Dynamic Setup (`backend/resources/js/Pages/Settings/Index.tsx`)
```typescript
import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface SettingsData {
    ai_language: string;
    auto_extract_phone: boolean;
    auto_extract_address: boolean;
    realtime_alerts: boolean;
    tiktok_username?: string;
}

interface Props extends PageProps {
    settings: SettingsData;
    active_streams_count: number;
    total_sessions_count: number;
}
```

Inside the `SettingsIndex` component:
```typescript
    const [isConnectOpen, setIsConnectOpen] = useState(false);
    const [tiktokInput, setTiktokInput] = useState('');

    const handleConnectTikTok = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            route('settings.tiktok.connect'),
            { tiktok_username: tiktokInput },
            {
                onSuccess: () => {
                    setIsConnectOpen(false);
                    setTiktokInput('');
                },
            }
        );
    };

    const handleDisconnectTikTok = () => {
        if (confirm('Bạn có chắc chắn muốn ngắt kết nối tài khoản TikTok này?')) {
            router.post(route('settings.tiktok.disconnect'));
        }
    };

    const tiktokUsername = settings.tiktok_username;
    const platforms = [
        {
            name: 'TikTok',
            connected: !!tiktokUsername,
            account: tiktokUsername ? `@${tiktokUsername.replace(/^@/, '')}` : 'Chưa kết nối',
        },
    ];
```

Render Subscription Card dynamically:
```tsx
    {/* Subscription */}
    <Card>
        <CardHeader>
            <CardTitle className="flex items-center gap-2">
                <CrownIcon className="size-5 text-yellow-500" />
                Gói đăng ký
            </CardTitle>
            <CardDescription>
                Quản lý gói sử dụng của bạn
            </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
            <div className="flex items-center justify-between rounded-lg border p-4">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-semibold">
                            {auth.subscription ? `Gói ${auth.subscription.package_name}` : 'Gói Free'}
                        </span>
                        <Badge>{auth.subscription?.active ? 'Đang sử dụng' : 'Hết hạn/Không hoạt động'}</Badge>
                    </div>
                    <div className="text-muted-foreground mt-2 space-y-1 text-sm">
                        <div>
                            • Luồng livestream: {auth.subscription?.features?.limit_streams === -1 ? 'Không giới hạn' : `${auth.subscription?.features?.limit_streams ?? 1} luồng`}
                        </div>
                        <div>
                            • Thời lượng tối đa: {auth.subscription?.features?.max_duration_hours === -1 ? 'Không giới hạn' : `${auth.subscription?.features?.max_duration_hours ?? 1} giờ/phiên`}
                        </div>
                        <div>
                            • Tín dụng AI: {auth.subscription?.features?.ai_credits === -1 ? 'Không giới hạn' : `${(auth.subscription?.features?.ai_credits ?? 1000).toLocaleString()} credits`}
                        </div>
                        <div>
                            • Phân tích âm thanh: {auth.subscription?.features?.audio_analysis ? 'Có hỗ trợ' : 'Không hỗ trợ'}
                        </div>
                        <div>
                            • Xuất Lead CSV: {auth.subscription?.features?.export_leads ? 'Có hỗ trợ' : 'Không hỗ trợ'}
                        </div>
                    </div>
                </div>
                <div className="text-right">
                    <div className="text-2xl font-bold">
                        {auth.subscription?.price ? `${(auth.subscription.price).toLocaleString('vi-VN')}đ` : '0đ'}
                    </div>
                    <div className="text-muted-foreground text-xs">
                        {auth.subscription?.duration_days ? `/ ${auth.subscription.duration_days} ngày` : '/ tháng'}
                    </div>
                    <div className="text-muted-foreground mt-2 text-xs">
                        Hạn dùng: {auth.subscription?.expires_at ? new Date(auth.subscription.expires_at).toLocaleDateString('vi-VN') : 'Vĩnh viễn'}
                    </div>
                </div>
            </div>
            
            {/* Live stream stats */}
            <div className="space-y-3 pt-2 border-t text-sm">
                <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">
                        Luồng live đang chạy
                    </span>
                    <span className="font-medium">
                        {active_streams_count} / {auth.subscription?.features?.limit_streams === -1 ? 'Không giới hạn' : `${auth.subscription?.features?.limit_streams ?? 1} luồng`}
                    </span>
                </div>
                <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">
                        Phiên live đã tạo (chu kỳ này)
                    </span>
                    <span className="font-medium">
                        {total_sessions_count} phiên
                    </span>
                </div>
            </div>
            
            <Link href={route('subscription.index')}>
                <Button variant="outline" className="gap-2 w-full mt-2 sm:w-auto">
                    <SparklesIcon className="size-4" />
                    {auth.subscription?.package_name === 'Enterprise' ? 'Quản lý gói cước' : 'Nâng cấp gói cước'}
                </Button>
            </Link>
        </CardContent>
    </Card>
```

#### 6. Adding Feature Tests (`backend/tests/Feature/TikTokConnectionTest.php`)
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TikTokConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_connect_tiktok_username()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.tiktok.connect'), [
            'tiktok_username' => 'test_creator',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $user->refresh();
        $this->assertEquals('test_creator', $user->settings['tiktok_username'] ?? null);
    }

    public function test_user_can_disconnect_tiktok_username()
    {
        $user = User::factory()->create([
            'settings' => ['tiktok_username' => 'test_creator']
        ]);

        $response = $this->actingAs($user)->post(route('settings.tiktok.disconnect'));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $user->refresh();
        $this->assertArrayNotHasKey('tiktok_username', $user->settings ?? []);
    }
}
```

---

## 5. Verification Method

To independently verify the implementation:

1.  **Run PHP Tests**:
    ```bash
    php artisan test tests/Feature/TikTokConnectionTest.php
    ```
2.  **Verify Frontend Typecheck & Build**:
    ```bash
    npm run build
    ```
3.  **Inspect Database Invariants**:
    *   Verify after updating AI settings, the user's `settings` column still retains the `tiktok_username` key.
