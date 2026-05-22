# Audit and Strategy Report: Settings & Subscription Statistics

## Summary
- **Scope**: User settings customization, dynamic subscription package details, platform connection (TikTok Integration via settings), dynamic usage stats, UI spacing/layout, and TS typings.
- **Mode**: Static code-path analysis & Strategy specification (Read-Only).
- **Confidence**: High.
- **Critical Issues Identified**: 1 (Overwriting Settings in `SettingsController.php` deletes non-AI configuration keys, e.g. `tiktok_username`).
- **High/Medium Issues**: 2 (Hardcoded stats and payment info in user settings card, lack of TikTok Connect/Disconnect API logic).
- **Recommendation**: Apply proposed controllers, middleware, routes, and components updates to dynamicize Settings and integrate TikTok linking safely.

---

## 1. Observation
We analyzed the target files:
- `backend/app/Http/Controllers/SettingsController.php`
- `backend/app/Http/Middleware/HandleInertiaRequests.php`
- `backend/routes/web.php`
- `backend/resources/js/Pages/Settings/Index.tsx`
- `backend/resources/js/types/index.d.ts`
- `backend/resources/js/Components/nav-user.tsx`

We also inspected related models `User.php`, `UserSubscription.php`, `SubscriptionPackage.php`, and `LiveSession.php`.

### Target Findings and Code Analysis:
1. **Critical Overwriting Bug in Settings Update (`SettingsController.php` line 26)**:
   ```php
   public function updateSettings(Request $request)
   {
       $validated = $request->validate([
           'ai_language' => ['required', 'in:vi,en,auto'],
           ...
       ]);
       $request->user()->update(['settings' => $validated]);
   }
   ```
   Overwriting the whole `settings` array with only AI-validated keys deletes any other key stored in the `settings` column (such as the proposed `tiktok_username`).

2. **Incomplete Subscription Data Shared in Middleware (`HandleInertiaRequests.php` lines 43-50)**:
   The shared `subscription` object does not contain package `price` or `duration_days`:
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

3. **Hardcoded Frontend UI Elements in Settings page (`Settings/Index.tsx` lines 123-166 & lines 54-56)**:
   - Pricing display is hardcoded to "299K VNĐ/tháng" for "Gói Pro".
   - Usage stats display is hardcoded to "18 / 30 phiên".
   - Platforms connection is hardcoded to a static list: `[{ name: 'TikTok', connected: true, account: '@shopthoitrang_abc' }]`.

4. **Missing TikTok connection APIs**:
   No route or controller actions are defined for connecting or disconnecting TikTok platforms using the `settings` JSON field.

---

## 2. Logic Chain
- **To make subscriptions dynamic**: We must retrieve `price` and `duration_days` from the active subscription's package and include them in the shared Inertia data array inside `HandleInertiaRequests.php`.
- **To avoid overwriting settings**: We must change the user's settings update method in `SettingsController.php` to perform an `array_merge` instead of replacing the entire field.
- **To add TikTok integration**:
  - We store `tiktok_username` inside `User::DEFAULT_SETTINGS` as `null` by default.
  - We define two new API endpoints: `PUT /settings/tiktok/connect` and `DELETE /settings/tiktok/disconnect`.
  - We update `Settings/Index.tsx` to call these routes using `useForm` and standard dialog states.
- **To query dynamic stats**:
  - Calculate `activeStreamsCount` by counting `LiveSession` records with status `connecting` or `live` belonging to the user.
  - Calculate `totalSessionsInCycle` by counting all `LiveSession` records created since the start of the current subscription cycle (`starts_at`).
  - Pass these variables as properties to `SettingsIndex`.

---

## 3. Caveats
- **Assumption**: The `LiveSession` model belongs to `User` through `user_id`. We verified this relationship in `LiveSession.php`, but it's not explicitly declared on `User.php`. We can query `LiveSession::where('user_id', $user->id)` safely.
- **API validation**: We assume that connection does not involve OAuth validation with TikTok API yet, but rather stores a verified identifier/handle string.

---

## 4. Conclusion & Proposed Strategy

### Action 1: Update TypeScript Definitions (`backend/resources/js/types/index.d.ts`)
Add `price` and `duration_days` to `UserSubscription`, define a `UserSettings` interface, and add it to `User`.

```typescript
export interface UserSettings {
    ai_language: 'vi' | 'en' | 'auto';
    auto_extract_phone: boolean;
    auto_extract_address: boolean;
    realtime_alerts: boolean;
    tiktok_username?: string | null;
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

---

### Action 2: Update Shared Data Middleware (`backend/app/Http/Middleware/HandleInertiaRequests.php`)
Include package `price` and `duration_days` from `$activeSub->package`.

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

---

### Action 3: Register New Settings & TikTok Routes (`backend/routes/web.php`)
Under the authenticated route group:

```php
    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/ai', [SettingsController::class, 'updateSettings'])->name('settings.update-ai');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.update-profile');
    Route::put('/settings/tiktok/connect', [SettingsController::class, 'connectTiktok'])->name('settings.tiktok.connect');
    Route::delete('/settings/tiktok/disconnect', [SettingsController::class, 'disconnectTiktok'])->name('settings.tiktok.disconnect');
```

---

### Action 4: Update Settings Controller (`backend/app/Http/Controllers/SettingsController.php`)
- Fetch dynamic live session stats.
- Fix settings overwriting bug.
- Add TikTok Connect & Disconnect API methods.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\LiveSession;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Calculate dynamic active stream counts
        $activeStreamsCount = LiveSession::where('user_id', $user->id)
            ->whereIn('status', ['connecting', 'live'])
            ->count();

        // Calculate total sessions in current subscription cycle
        $activeSub = $user->resolveActiveSubscription();
        $cycleStartDate = $activeSub?->starts_at ?? now()->startOfMonth();

        $totalSessionsInCycle = LiveSession::where('user_id', $user->id)
            ->where('created_at', '>=', $cycleStartDate)
            ->count();

        return Inertia::render('Settings/Index', [
            'settings' => $user->getSettingsWithDefaults(),
            'activeStreamsCount' => $activeStreamsCount,
            'totalSessionsInCycle' => $totalSessionsInCycle,
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
        $user->update([
            'settings' => array_merge($user->settings ?? [], $validated)
        ]);

        return back()->with('success', 'Đã lưu cài đặt AI.');
    }

    public function connectTiktok(Request $request)
    {
        $validated = $request->validate([
            'tiktok_username' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $settings = $user->settings ?? [];
        $settings['tiktok_username'] = $validated['tiktok_username'];
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Đã kết nối tài khoản TikTok thành công.');
    }

    public function disconnectTiktok(Request $request)
    {
        $user = $request->user();
        $settings = $user->settings ?? [];
        $settings['tiktok_username'] = null;
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Đã ngắt kết nối tài khoản TikTok.');
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Đã cập nhật thông tin tài khoản.');
    }
}
```

---

### Action 5: Dynamicize User Settings UI Page (`backend/resources/js/Pages/Settings/Index.tsx`)
- Read new props (`activeStreamsCount`, `totalSessionsInCycle`).
- Hook TikTok connect form (`useForm`) & Dialog state.
- Render dynamic package stats, platform connection badge status, and dynamic features listing.

```typescript
import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Switch } from '@/components/ui/switch';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { PageProps } from '@/types';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import {
    CheckCircle2Icon,
    CheckIcon,
    CrownIcon,
    LinkIcon,
    LoaderIcon,
    SparklesIcon,
    XCircleIcon,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface SettingsData {
    ai_language: string;
    auto_extract_phone: boolean;
    auto_extract_address: boolean;
    realtime_alerts: boolean;
    tiktok_username?: string | null;
}

interface Props extends PageProps {
    settings: SettingsData;
    activeStreamsCount: number;
    totalSessionsInCycle: number;
}

export default function SettingsIndex({ settings, activeStreamsCount, totalSessionsInCycle }: Props) {
    const { auth } = usePage<Props>().props;
    const [isTiktokModalOpen, setIsTiktokModalOpen] = useState(false);

    // AI Settings Form
    const aiForm = useForm({
        ai_language: settings.ai_language,
        auto_extract_phone: settings.auto_extract_phone,
        auto_extract_address: settings.auto_extract_address,
        realtime_alerts: settings.realtime_alerts,
    });

    // Profile Form
    const profileForm = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    // TikTok Connection Form
    const tiktokForm = useForm({
        tiktok_username: settings.tiktok_username || '',
    });

    function submitAiSettings(e: React.FormEvent) {
        e.preventDefault();
        aiForm.put(route('settings.update-ai'), { preserveScroll: true });
    }

    function submitProfile(e: React.FormEvent) {
        e.preventDefault();
        profileForm.put(route('settings.update-profile'), {
            preserveScroll: true,
        });
    }

    function handleTiktokConnect(e: React.FormEvent) {
        e.preventDefault();
        tiktokForm.put(route('settings.tiktok.connect'), {
            preserveScroll: true,
            onSuccess: () => {
                setIsTiktokModalOpen(false);
                toast.success('Đã kết nối tài khoản TikTok thành công!');
            },
            onError: (err) => {
                toast.error(err.tiktok_username || 'Không thể kết nối.');
            }
        });
    }

    function handleTiktokDisconnect() {
        if (confirm('Bạn có chắc chắn muốn ngắt kết nối tài khoản TikTok này?')) {
            router.delete(route('settings.tiktok.disconnect'), {
                preserveScroll: true,
                onSuccess: () => {
                    tiktokForm.setData('tiktok_username', '');
                    toast.success('Đã ngắt kết nối tài khoản TikTok.');
                },
                onError: () => {
                    toast.error('Không thể ngắt kết nối.');
                }
            });
        }
    }

    const subscription = auth.subscription;
    const limitStreams = subscription?.features?.limit_streams ?? 1;

    // Feature descriptions helper based on features
    const getFeaturesString = () => {
        if (!subscription) return 'Không có gói hoạt động';
        const f = subscription.features;
        const list = [];
        list.push(f.limit_streams === -1 ? 'Không giới hạn luồng' : `${f.limit_streams} luồng live`);
        list.push(`${f.max_duration_hours} giờ/phiên`);
        list.push(`${f.ai_credits?.toLocaleString()} bình luận AI`);
        if (f.audio_analysis) list.push('Phân tích âm thanh');
        if (f.export_leads) list.push('Xuất Leads CSV');
        return list.join(' · ');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Cài đặt" />
            <header className="border-border/40 bg-background/95 sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                <div className="flex items-center gap-2 px-4">
                    <SidebarTrigger className="-ml-1" />
                    <Separator
                        orientation="vertical"
                        className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                    />
                    <Breadcrumb>
                        <BreadcrumbList>
                            <BreadcrumbItem className="hidden md:block">
                                <BreadcrumbLink href={route('dashboard')}>
                                    Trang chủ
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator className="hidden md:block" />
                            <BreadcrumbItem>
                                <BreadcrumbPage>Cài đặt</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Cài đặt
                    </h1>
                    <p className="text-muted-foreground">
                        Quản lý tài khoản, kết nối nền tảng và tùy chỉnh AI
                    </p>
                </div>

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
                                        Gói {subscription?.package_name ?? 'Free'}
                                    </span>
                                    {subscription?.active && <Badge>Đang sử dụng</Badge>}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {getFeaturesString()}
                                </p>
                            </div>
                            <div className="text-right">
                                <div className="text-2xl font-bold">
                                    {subscription?.price === 0 ? '0đ' : `${subscription?.price?.toLocaleString('vi-VN')}đ`}
                                </div>
                                <div className="text-muted-foreground text-xs">
                                    /{subscription?.duration_days ?? 30} ngày
                                </div>
                            </div>
                        </div>
                        <div className="grid gap-2 border-t pt-3 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Số luồng live đang chạy / Giới hạn
                                </span>
                                <span className="font-medium">
                                    {activeStreamsCount} / {limitStreams === -1 ? 'Vô hạn' : `${limitStreams} luồng`}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Tổng phiên live đã tạo trong chu kỳ này
                                </span>
                                <span className="font-medium">
                                    {totalSessionsInCycle} phiên
                                </span>
                            </div>
                            {subscription?.expires_at && (
                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Ngày hết hạn</span>
                                    <span>{new Date(subscription.expires_at).toLocaleDateString('vi-VN')}</span>
                                </div>
                            )}
                        </div>
                        <Button 
                            variant="outline" 
                            className="gap-2"
                            onClick={() => router.get('/subscription')}
                        >
                            <SparklesIcon className="size-4" />
                            {subscription?.package_name !== 'Free' ? 'Quản lý gói' : 'Nâng cấp ngay'}
                        </Button>
                    </CardContent>
                </Card>

                {/* Platform Connections */}
                <Card>
                    <CardHeader>
                        <CardTitle>Kết nối nền tảng</CardTitle>
                        <CardDescription>
                            Kết nối tài khoản bán hàng để AI thu thập dữ liệu
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="flex items-center gap-3">
                                {settings.tiktok_username ? (
                                    <CheckCircle2Icon className="size-5 text-green-500" />
                                ) : (
                                    <XCircleIcon className="text-muted-foreground size-5" />
                                )}{' '}
                                <div>
                                    <div className="font-medium">TikTok</div>
                                    <div className="text-muted-foreground text-sm">
                                        {settings.tiktok_username ? `@${settings.tiktok_username.replace(/^@/, '')}` : 'Chưa kết nối'}
                                    </div>
                                </div>
                            </div>
                            {settings.tiktok_username ? (
                                <Button variant="outline" size="sm" onClick={handleTiktokDisconnect}>
                                    Ngắt kết nối
                                </Button>
                            ) : (
                                <Button variant="default" size="sm" onClick={() => setIsTiktokModalOpen(true)}>
                                    <LinkIcon className="mr-1.5 size-3.5" />
                                    Kết nối
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* AI Preferences */}
                <form onSubmit={submitAiSettings}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Tùy chỉnh AI</CardTitle>
                            <CardDescription>
                                Cấu hình cách AI phân tích bình luận
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Ngôn ngữ phân tích</Label>
                                <Select
                                    value={aiForm.data.ai_language}
                                    onValueChange={(value) =>
                                        aiForm.setData('ai_language', value)
                                    }
                                >
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="vi">
                                            Tiếng Việt
                                        </SelectItem>
                                        <SelectItem value="en">
                                            English
                                        </SelectItem>
                                        <SelectItem value="auto">
                                            Tự động nhận diện
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>Trích xuất SĐT tự động</Label>
                                    <p className="text-muted-foreground text-xs">
                                        AI tự động tìm và trích xuất số điện
                                        thoại từ bình luận
                                    </p>
                                </div>
                                <Switch
                                    checked={aiForm.data.auto_extract_phone}
                                    onCheckedChange={(checked) =>
                                        aiForm.setData(
                                            'auto_extract_phone',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>Trích xuất địa chỉ tự động</Label>
                                    <p className="text-muted-foreground text-xs">
                                        AI tự động tìm địa chỉ giao hàng từ bình
                                        luận
                                    </p>
                                </div>
                                <Switch
                                    checked={aiForm.data.auto_extract_address}
                                    onCheckedChange={(checked) =>
                                        aiForm.setData(
                                            'auto_extract_address',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>Cảnh báo realtime</Label>
                                    <p className="text-muted-foreground text-xs">
                                        Nhận cảnh báo khi có nhiều bình luận
                                        tiêu cực hoặc câu hỏi chưa trả lời
                                    </p>
                                </div>
                                <Switch
                                    checked={aiForm.data.realtime_alerts}
                                    onCheckedChange={(checked) =>
                                        aiForm.setData(
                                            'realtime_alerts',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={aiForm.processing}
                                className="gap-2"
                            >
                                {aiForm.processing ? (
                                    <LoaderIcon className="size-4 animate-spin" />
                                ) : aiForm.recentlySuccessful ? (
                                    <CheckIcon className="size-4" />
                                ) : null}
                                {aiForm.recentlySuccessful
                                    ? 'Đã lưu'
                                    : 'Lưu cài đặt AI'}
                            </Button>
                            {aiForm.errors.ai_language && (
                                <p className="text-destructive text-sm">
                                    {aiForm.errors.ai_language}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </form>

                {/* Profile */}
                <form onSubmit={submitProfile}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Thông tin tài khoản</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Tên</Label>
                                <Input
                                    id="name"
                                    value={profileForm.data.name}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                />
                                {profileForm.errors.name && (
                                    <p className="text-destructive text-sm">
                                        {profileForm.errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={profileForm.data.email}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                />
                                {profileForm.errors.email && (
                                    <p className="text-destructive text-sm">
                                        {profileForm.errors.email}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={profileForm.processing}
                                className="gap-2"
                            >
                                {profileForm.processing ? (
                                    <LoaderIcon className="size-4 animate-spin" />
                                ) : profileForm.recentlySuccessful ? (
                                    <CheckIcon className="size-4" />
                                ) : null}
                                {profileForm.recentlySuccessful
                                    ? 'Đã lưu'
                                    : 'Lưu thay đổi'}
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>

            {/* TikTok username connection dialog */}
            <Dialog open={isTiktokModalOpen} onOpenChange={setIsTiktokModalOpen}>
                <DialogContent className="max-w-md">
                    <form onSubmit={handleTiktokConnect}>
                        <DialogHeader>
                            <DialogTitle>Kết nối tài khoản TikTok</DialogTitle>
                            <DialogDescription>
                                Vui lòng nhập username TikTok của bạn để đồng bộ các livestream bán hàng.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <Label htmlFor="tiktok_username">Username TikTok</Label>
                            <Input
                                id="tiktok_username"
                                placeholder="Ví dụ: @shopabc"
                                value={tiktokForm.data.tiktok_username}
                                onChange={(e) => tiktokForm.setData('tiktok_username', e.target.value)}
                                required
                                className="mt-1"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsTiktokModalOpen(false)}>
                                Hủy
                            </Button>
                            <Button type="submit" disabled={tiktokForm.processing}>
                                {tiktokForm.processing && <LoaderIcon className="mr-1.5 size-4 animate-spin" />}
                                Lưu kết nối
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
```

---

### Action 6: Improve User Menu label checking (`backend/resources/js/Components/nav-user.tsx`)
Future-proof the package checking logic at lines 49-52 by referencing the backend-provided price / status or ensuring package names other than "Free" trigger "Quản lý gói".

```typescript
    const isProOrEnterprise =
        auth.subscription?.active &&
        auth.subscription.package_name !== 'Free';
```

---

### Action 7: Write Integration Test Suite (`backend/tests/Feature/TikTokConnectionTest.php`)
Create an automated test suite verifying settings update safety and TikTok connect/disconnect endpoints.

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TikTokConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_settings_page()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('settings.index'));

        $response->assertOk();
    }

    public function test_user_can_connect_tiktok_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put(route('settings.tiktok.connect'), [
                'tiktok_username' => '@shopabc',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();
        
        $user->refresh();
        $this->assertEquals('@shopabc', $user->getSettingsWithDefaults()['tiktok_username']);
    }

    public function test_user_can_disconnect_tiktok_account()
    {
        $user = User::factory()->create([
            'settings' => ['tiktok_username' => '@shopabc']
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('settings.tiktok.disconnect'));

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertNull($user->getSettingsWithDefaults()['tiktok_username'] ?? null);
    }
}
```

---

## 5. Verification Method

To verify the strategy and implementation:
1. **Types Verification**: Compile assets using `npm run build` or run `npx tsc --noEmit` to ensure no TypeScript compilation errors occur.
2. **Backend Automated Tests**: Run:
   ```bash
   php artisan test tests/Feature/TikTokConnectionTest.php
   ```
   to check settings endpoints function correctly under standard session authorization.
3. **Database Integrity Audit**: Execute queries directly or check database records post-checkout / settings-update to assert that updating AI configurations does not destroy the `tiktok_username` JSON key.
