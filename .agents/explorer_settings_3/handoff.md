# Handoff Report — Explorer 3 (Settings & TikTok Connection Strategy)

## 1. Observation

Direct observations and code references gathered from the source files:

### A. Subscription Data in Inertia Middleware
In `backend/app/Http/Middleware/HandleInertiaRequests.php` (lines 43-50):
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
This is missing `price`, `duration_days`, and dynamic active stream/cycle session counts.

### B. User Settings & TikTok Username
In `backend/app/Models/User.php`:
The `settings` column is cast as an array. The default settings do not explicitly define a key for TikTok username, but `getSettingsWithDefaults()` merges defaults with the JSON values.
In `backend/app/Http/Controllers/SettingsController.php` (lines 19-32):
```php
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'ai_language' => ['required', 'in:vi,en,auto'],
            'auto_extract_phone' => ['required', 'boolean'],
            'auto_extract_address' => ['required', 'boolean'],
            'realtime_alerts' => ['required', 'boolean'],
        ]);

        $request->user()->update(['settings' => $validated]);

        return back()->with('success', 'Đã lưu cài đặt AI.');
    }
```
Currently, `updateSettings()` overwrites the entire JSON column with only the AI settings, which would erase the `tiktok_username` if saved there.

### C. Hardcoded Features and Pricing in Settings UI
In `backend/resources/js/Pages/Settings/Index.tsx` (lines 135-164):
The pricing, duration, and feature list are completely static/hardcoded with Vietnamese text strings.

### D. Spacing Paddings
In `backend/resources/js/Pages/Lives/Setup.tsx` (line 140) and `backend/resources/js/Pages/Profile/Edit.tsx` (line 49):
```typescript
className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 pt-4"
```
These pages use `p-4 pt-4` instead of the standard `p-6` layout padding found in other main dashboard pages.

### E. Checkout Modal Dimensions
In `backend/resources/js/Pages/Subscription/Index.tsx` (line 773-774):
```typescript
<DialogContent className="flex max-h-[85vh] max-w-md flex-col gap-0 overflow-hidden p-0 sm:max-h-[90vh]">
```
The constraint `max-h-[85vh]` combined with large padding (`p-4` on header/footer/content) and large QR code size (`max-h-[155px]`) causes footer elements to truncate on low-resolution laptop screens.

---

## 2. Logic Chain

1. **Pricing, Duration & Features Integration:**
   - To make pricing, duration, and features dynamic in `/settings`, these properties must be added to the Inertia subscription data structure.
   - Tracing from `HandleInertiaRequests.php` back to `UserSubscription.php` and `SubscriptionPackage.php` confirms that `$activeSub->package` stores the `price` and `duration_days` attributes.
   - The features are resolved dynamically by calling `$user->getSubscriptionFeatures()`.
   - By appending `price` and `duration_days` directly to the `auth.subscription` array, the frontend settings page can consume them dynamically.

2. **TikTok Connection Persistence & Controller Safety:**
   - Standardizing TikTok usernames to always start with `@` (e.g. converting `username` to `@username`) makes usernames recognizable.
   - Storing the username in the `settings` JSON column under the key `tiktok_username` leverages existing db architecture.
   - To avoid losing the connected TikTok username during AI settings updates, `SettingsController@updateSettings` must use `array_merge` instead of replacing the entire `settings` array.

3. **Dynamic Stream Statistics:**
   - Client-side gating at `Lives/Setup.tsx` requires checking the current count of active streams.
   - Querying `LiveSession` status in `HandleInertiaRequests.php` and checking active streams count vs. package limit provides a global state.
   - Querying sessions in the current cycle (`created_at >= starts_at` of subscription) provides accurate usage telemetry for settings views.

4. **Premium UI standardizations:**
   - Changing `p-4 pt-4` container padding to `p-6` in `Setup.tsx` and `Profile/Edit.tsx` unifies layout aesthetics.
   - Modifying the checkout dialog's max height limit to `max-h-[92vh]`, reducing the QR code placeholder scale, and reducing dialog padding from `p-4` to `p-3` prevents overflow on 768px height displays.

---

## 3. Caveats

- **TikTok username validation:** The current implementation stores the username purely as a user setting without making outbound API requests to TikTok for security/auth verification. If actual account verification is needed in the future, OAuth or TikTok API integrations must be added.
- **Cycle start default:** If a user has no active subscription start date, calculations default to the beginning of the current calendar month.

---

## 4. Conclusion & Actionable Blueprint

### Part A. Web Routes & Backend Implementation

1. **Add Web Routes:**
   Add these routes inside the `auth` middleware group in `routes/web.php`:
   ```php
   Route::post('/settings/tiktok/connect', [SettingsController::class, 'connectTikTok'])->name('settings.tiktok.connect');
   Route::post('/settings/tiktok/disconnect', [SettingsController::class, 'disconnectTikTok'])->name('settings.tiktok.disconnect');
   ```

2. **Update `SettingsController.php`:**
   Implement TikTok connect/disconnect methods and adjust `updateSettings` to merge rather than override the array.
   ```php
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

       $user->update([
           'settings' => array_merge($currentSettings, $validated)
       ]);

       return back()->with('success', 'Đã lưu cài đặt AI.');
   }

   public function connectTikTok(Request $request)
   {
       $validated = $request->validate([
           'tiktok_username' => ['required', 'string', 'max:255', 'regex:/^@?[a-zA-Z0-9_\.]+$/'],
       ]);

       $user = $request->user();
       $settings = $user->settings ?? [];

       $username = trim($validated['tiktok_username']);
       if (!str_starts_with($username, '@')) {
           $username = '@' . $username;
       }

       $settings['tiktok_username'] = $username;
       $user->update(['settings' => $settings]);

       return back()->with('success', 'Kết nối tài khoản TikTok thành công.');
   }

   public function disconnectTikTok(Request $request)
   {
       $user = $request->user();
       $settings = $user->settings ?? [];

       if (array_key_exists('tiktok_username', $settings)) {
           unset($settings['tiktok_username']);
           $user->update(['settings' => $settings]);
       }

       return back()->with('success', 'Đã ngắt kết nối tài khoản TikTok.');
   }
   ```

3. **Update Default Settings in `User.php`:**
   Add default null value in `DEFAULT_SETTINGS` constant inside `App\Models\User`:
   ```php
   public const DEFAULT_SETTINGS = [
       'ai_language' => 'vi',
       'auto_extract_phone' => true,
       'auto_extract_address' => true,
       'realtime_alerts' => true,
       'tiktok_username' => null, // Added default TikTok value
   ];
   ```

4. **Update `HandleInertiaRequests.php`:**
   Extend shared `auth.subscription` data in `share()` method to provide pricing, duration, and dynamic stats:
   ```php
   $activeSub = $user->resolveActiveSubscription();

   $activeStreamsCount = \App\Models\LiveSession::where('user_id', $user->id)
       ->whereIn('status', ['connecting', 'live'])
       ->count();

   $totalSessionsInCycle = 0;
   if ($activeSub && $activeSub->starts_at) {
       $totalSessionsInCycle = \App\Models\LiveSession::where('user_id', $user->id)
           ->where('created_at', '>=', $activeSub->starts_at)
           ->count();
   } else {
       $totalSessionsInCycle = \App\Models\LiveSession::where('user_id', $user->id)
           ->where('created_at', '>=', now()->startOfMonth())
           ->count();
   }

   $subscription = [
       'active' => (bool) $activeSub?->isActive(),
       'package_id' => $activeSub?->subscription_package_id,
       'package_name' => $activeSub?->package?->name ?? 'Free',
       'price' => $activeSub?->package?->price ?? 0,
       'duration_days' => $activeSub?->package?->duration_days ?? 30,
       'expires_at' => $activeSub?->expires_at?->toISOString(),
       'used_ai_credits' => $activeSub?->used_ai_credits ?? 0,
       'features' => $user->getSubscriptionFeatures(),
       'active_streams_count' => $activeStreamsCount,
       'total_sessions_in_cycle' => $totalSessionsInCycle,
   ];
   ```

---

### Part B. Frontend & TypeScript Implementations

1. **Update `index.d.ts`:**
   Add missing attributes to user settings and user subscription interfaces:
   ```typescript
   export interface User {
       id: number;
       name: string;
       email: string;
       email_verified_at?: string;
       created_at?: string;
       role: 'user' | 'admin';
       plan_name?: string;
       settings?: {
           ai_language?: 'vi' | 'en' | 'auto';
           auto_extract_phone?: boolean;
           auto_extract_address?: boolean;
           realtime_alerts?: boolean;
           tiktok_username?: string | null;
           [key: string]: any;
       };
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
       active_streams_count?: number;
       total_sessions_in_cycle?: number;
   }
   ```

2. **Update Settings Page UI (`Settings/Index.tsx`):**
   - Add state to handle the TikTok connection dialog.
   - Render pricing, duration, and dynamic features string based on `auth.subscription`.
   - Update platforms card to dynamically show TikTok connection status and toggle between Connect Dialog or Disconnect Confirmation.
   - Example implementation snippet:
   ```typescript
   import {
       Dialog,
       DialogContent,
       DialogHeader,
       DialogTitle,
       DialogFooter,
   } from '@/Components/ui/dialog';
   import { Input } from '@/Components/ui/input';
   import { Label } from '@/Components/ui/label';

   // Inside component...
   const { auth } = usePage<PageProps>().props;
   const subscription = auth.subscription;
   const packageName = subscription?.package_name ?? 'Free';
   const priceVal = subscription?.price ?? 0;
   const durationDays = subscription?.duration_days ?? 30;

   // TikTok username connect states & hooks
   const [isConnectOpen, setIsConnectOpen] = React.useState(false);
   const connectTikTokForm = useForm({
       tiktok_username: '',
   });

   const handleConnectClick = () => {
       connectTikTokForm.reset();
       setIsConnectOpen(true);
   };

   const handleConnectSubmit = (e: React.FormEvent) => {
       e.preventDefault();
       connectTikTokForm.post(route('settings.tiktok.connect'), {
           onSuccess: () => {
               setIsConnectOpen(false);
               connectTikTokForm.reset();
           },
       });
   };

   const handleDisconnect = () => {
       if (confirm('Bạn có chắc chắn muốn ngắt kết nối tài khoản TikTok này?')) {
           router.post(route('settings.tiktok.disconnect'));
       }
   };

   // Dynamic pricing/features helpers
   const formatPrice = (price: number) => {
       if (price === 0) return 'Miễn phí';
       if (price >= 1000) {
           return `${(price / 1000).toLocaleString('vi-VN')}K`;
       }
       return `${price.toLocaleString('vi-VN')}đ`;
   };

   const featuresList = [];
   const features = subscription?.features;
   if (features) {
       if (features.limit_streams !== undefined) {
           featuresList.push(features.limit_streams === -1 ? 'Không giới hạn luồng' : `${features.limit_streams} luồng live đồng thời`);
       }
       if (features.max_duration_hours !== undefined) {
           featuresList.push(`Tối đa ${features.max_duration_hours}h/phiên`);
       }
       if (features.ai_credits !== undefined) {
           featuresList.push(features.ai_credits === -1 ? 'Không giới hạn AI credits' : `${features.ai_credits.toLocaleString('vi-VN')} AI credits`);
       }
       if (features.audio_analysis) {
           featuresList.push('Phân tích âm thanh');
       }
       if (features.export_leads) {
           featuresList.push('Xuất leads CSV');
       }
   }
   const featuresString = featuresList.length > 0 ? featuresList.join(' · ') : 'Không có tính năng đi kèm';
   ```

3. **Gating Check in Setup page (`Lives/Setup.tsx`):**
   Ensure fallback handles both props and shared auth variables:
   ```typescript
   const activeStreamsCountVal = auth?.subscription?.active_streams_count ?? active_streams_count;
   const isGated = limitStreams !== -1 && activeStreamsCountVal >= limitStreams;
   ```

4. **Address UI padding standardizations:**
   Change the container padding in:
   - `backend/resources/js/Pages/Lives/Setup.tsx` (line 140)
   - `backend/resources/js/Pages/Profile/Edit.tsx` (line 49)
   From:
   `className="... p-4 pt-4"`
   To:
   `className="... p-6"`

5. **Fix Checkout Modal truncation in `Subscription/Index.tsx`:**
   Adjust layout dimensions:
   - Change `max-h-[85vh]` to `max-h-[92vh]`.
   - Shrink VietQR code wrapper and image constraints from `max-h-[155px] max-w-[155px]` to `max-h-[130px] max-w-[130px]`.
   - Change content wrapper padding from `p-4` to `p-3 px-4` and inner gaps from `space-y-3` to `space-y-2.5` or `space-y-2`.
   - Reduce detail info rows padding to `py-0.5`.

---

## 5. Verification Method

1. **Artisan Feature Tests:**
   Create a new feature test `tests/Feature/TikTokConnectionTest.php`:
   ```php
   <?php

   namespace Tests\Feature;

   use App\Models\User;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   class TikTokConnectionTest extends TestCase
   {
       use RefreshDatabase;

       public function test_user_can_connect_tiktok_account()
       {
           $user = User::factory()->create();

           $response = $this->actingAs($user)->post(route('settings.tiktok.connect'), [
               'tiktok_username' => 'shopabc',
           ]);

           $response->assertRedirect();
           $user->refresh();
           $this->assertEquals('@shopabc', $user->settings['tiktok_username']);
       }

       public function test_user_can_disconnect_tiktok_account()
       {
           $user = User::factory()->create([
               'settings' => ['tiktok_username' => '@shopabc']
           ]);

           $response = $this->actingAs($user)->post(route('settings.tiktok.disconnect'));

           $response->assertRedirect();
           $user->refresh();
           $this->assertArrayNotHasKey('tiktok_username', $user->settings ?? []);
       }
   }
   ```
   Run verification test via command:
   ```bash
   php artisan test tests/Feature/TikTokConnectionTest.php
   ```

2. **TypeScript Compilation Check:**
   Compile assets using:
   ```bash
   npm run build
   ```
   This ensures that no TypeScript syntax errors or property access errors exist after updating `index.d.ts`.
