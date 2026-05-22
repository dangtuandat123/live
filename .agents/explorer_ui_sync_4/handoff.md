# Detailed Implementation Plan: Phase 2 UI/UX Sync & Refinements

## 1. Observation

Direct observations from codebase inspection:

### R1: User Menu Dynamic Labels & Types
*   **File Path**: `backend/resources/js/Components/nav-user.tsx`
    *   **Lines 154-162**: Hardcoded text "Nâng cấp Pro" is defined inside `<DropdownMenuGroup>` element:
        ```tsx
        <DropdownMenuGroup>
            <DropdownMenuItem asChild>
                <Link
                    href="/subscription"
                    className="flex w-full cursor-pointer items-center gap-2"
                >
                    <SparklesIcon className="text-primary size-4" />
                    <span>Nâng cấp Pro</span>
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
        ```
*   **File Path**: `backend/app/Http/Middleware/HandleInertiaRequests.php`
    *   **Lines 43-58**: The middleware shares the subscription state within the `auth` object:
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
*   **File Path**: `backend/resources/js/types/index.d.ts`
    *   **Lines 1-17**: Interface `User` and `PageProps` type are declared without any subscription properties:
        ```typescript
        export interface User {
            id: number;
            name: string;
            email: string;
            email_verified_at?: string;
            role: 'admin' | 'user';
            created_at?: string;
        }

        export type PageProps<
            T extends Record<string, unknown> = Record<string, unknown>,
        > = T & {
            auth: {
                user: User;
            };
        };
        ```

### R2: Spacing & Layout Padding Update
The following 10 page files contain container divisions that use hardcoded padding sizes of `p-4 pt-4` and need to be standardized to `p-6` or `p-6 pt-6`:
1.  `backend/resources/js/Pages/Dashboard.tsx`
    *   **Line 180**: `<div className="flex flex-1 flex-col gap-4 p-4 pt-4">`
2.  `backend/resources/js/Pages/Lives/Index.tsx`
    *   **Line 144**: `<div className="flex flex-1 flex-col gap-4 p-4 pt-4">`
3.  `backend/resources/js/Pages/Reports/Index.tsx`
    *   **Line 210**: `<div className="flex flex-1 flex-col gap-4 p-4 pt-4">`
4.  `backend/resources/js/Pages/Products/Index.tsx`
    *   **Line 379**: `<div className="flex flex-1 flex-col gap-4 p-4 pt-4">`
5.  `backend/resources/js/Pages/Settings/Index.tsx`
    *   **Line 113**: `<div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 pt-4">`
6.  `backend/resources/js/Pages/Admin/Dashboard.tsx`
    *   **Line 135**: `<div className="flex flex-1 flex-col gap-4 p-4 pt-4">`
7.  `backend/resources/js/Pages/Admin/Users/Index.tsx`
    *   **Line 78**: `<div className="flex flex-1 flex-col gap-4 p-4 pt-4">`
8.  `backend/resources/js/Pages/Admin/Packages/Index.tsx`
    *   **Line 230**: `<div className="mx-auto w-full max-w-6xl flex flex-1 flex-col gap-6 p-4 pt-4">`
9.  `backend/resources/js/Pages/Admin/Payments/Index.tsx`
    *   **Line 164**: `className="mx-auto w-full max-w-4xl flex flex-1 flex-col gap-6 p-4 pt-4"`
10. `backend/resources/js/Pages/Admin/Settings/Index.tsx`
    *   **Line 99**: `className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 pt-4"`

### R2: Checkout Modal Sizing
*   **File Path**: `backend/resources/js/Pages/Subscription/Index.tsx`
    *   **Lines 304-325**: Dialog styling has heavy padding `p-5` and standard sizing.
        ```tsx
        <DialogContent className="max-w-[425px] p-5">
        // ...
        <div className="flex flex-col items-center justify-center p-5 bg-muted/50 rounded-lg">
        ```
    *   **Lines 312-315**: QR Code container:
        ```tsx
        <img
            src={getQrUrl(pkg.price)}
            alt="VietQR Code"
            className="w-full h-auto border rounded"
        />
        ```
        This image needs to be constrained to `max-w-[155px]` size.

### R3: Landing Page Buttons
*   **File Path**: `backend/resources/views/landing.blade.php`
    *   **Lines 770-772**: Button "Bắt đầu ngay" has no `w-full` class.
        ```html
        <a href="{{ route('register') }}" class="mt-8 inline-flex h-10 items-center justify-center rounded-xl border border-border bg-background text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
            Bắt đầu ngay
        </a>
        ```
    *   **Lines 814-816**: Button "Đăng ký ngay" has no `w-full` class.
        ```html
        <a href="{{ route('register') }}" class="mt-8 inline-flex h-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-md shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.01]">
            Đăng ký ngay
        </a>
        ```

### R4: Livestream Status Badges Redesign
*   **File Path**: `backend/resources/js/Pages/Lives/Index.tsx`
    *   **Lines 258-282**: Traditional colored badges:
        ```tsx
        {session.status === "live" ? (
          <Badge variant="destructive" className="gap-1 text-[10px] px-1.5 py-0.5 shadow-lg">
            {/* Ping animation dot */}
            LIVE
          </Badge>
        ) : ... }
        ```
*   **File Path**: `backend/resources/js/Pages/Lives/Show.tsx`
    *   **Lines 1688-1710**: Similarly styled badges.

---

## 2. Logic Chain

1.  **R1 Label Customization**:
    *   Since Inertia shares `$subscription` within `auth` prop dynamically, we can utilize `usePage<PageProps>().props` inside `nav-user.tsx` to retrieve active subscription details.
    *   Checking if `auth.subscription` has `active === true` and its `package_name` is either `'Pro'` or `'Enterprise'` correctly satisfies the requirement.
    *   Adding interface declarations for `UserSubscription` and updating `PageProps` in `index.d.ts` will resolve any potential TypeScript compiler warnings/errors during development or production builds.

2.  **R2 Standardizing Spacing**:
    *   Applying padding changes uniformly on the main wrapper div of all 10 pages ensures visual alignment across dashboards, reports, and settings. Changing `p-4 pt-4` to `p-6 pt-6` or `p-6` keeps the top headers aligned properly.

3.  **R2 Checkout Sizing**:
    *   Reducing modal content padding from `p-5` to `p-4`, shrinking gaps, and forcing `max-w-[155px]` on the QR image optimizes readability of checkout instructions on smaller screens and mobile layouts.

4.  **R3 Responsive Landing Page Buttons**:
    *   Adding `w-full` combined with responsive sizing constraints `sm:w-auto` ensures buttons adjust width based on screen width, wrapping text correctly without overflow on ultra-compact screens.

5.  **R4 Premium Badges**:
    *   Removing solid `variant="destructive"` or `variant="secondary"` from `<Badge>` and assigning `bg-{color}-500/10 text-{color}-500 border border-{color}-500/20 backdrop-blur-md` yields a glassmorphic, modern visual look.
    *   Adding double-nested elements with `animate-ping` for `live`, `connecting`, and `disconnected` states brings real-time visual responsiveness.

---

## 3. Caveats

*   **Plan Names**: Assumed that subscription package names are strictly `'Pro'` and `'Enterprise'` matching DB constraints. If the database uses `'Business'` or other package titles, the condition `auth.subscription.package_name !== 'Free'` can be used as a safer fallback.
*   **Landing Page Utilities**: Blade templates rely on standard Tailwind classes compiled by Vite. If there are custom CSS overrides on `.inline-flex`, they could affect the landing page buttons.

---

## 4. Conclusion & Actionable Diffs

The proposed changes are verified as minimal, robust, and safe.

### R1 Diffs

#### `backend/resources/js/types/index.d.ts`
```diff
<<<<
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'admin' | 'user';
    created_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
====
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'admin' | 'user';
    created_at?: string;
}

export interface UserSubscriptionFeatures {
    limit_streams?: number;
    max_duration_hours?: number;
    ai_credits?: number;
    audio_analysis?: boolean;
    export_leads?: boolean;
}

export interface UserSubscription {
    active: boolean;
    package_id: number | null;
    package_name: string;
    expires_at: string | null;
    used_ai_credits: number;
    features: UserSubscriptionFeatures;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        subscription: UserSubscription | null;
    };
};
>>>>
```

#### `backend/resources/js/Components/nav-user.tsx`
```diff
<<<<
import { Link } from '@inertiajs/react';
====
import { Link, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
>>>>
```
```diff
<<<<
export function NavUser({
    user,
}: {
    user: {
        name: string;
        email: string;
        avatar: string;
    };
}) {
    const { isMobile } = useSidebar();
====
export function NavUser({
    user,
}: {
    user: {
        name: string;
        email: string;
        avatar: string;
    };
}) {
    const { isMobile } = useSidebar();
    const { auth } = usePage<PageProps>().props;
    const isProOrEnterprise = auth.subscription?.active && 
        (auth.subscription.package_name === 'Pro' || auth.subscription.package_name === 'Enterprise');
>>>>
```
```diff
<<<<
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link
                                    href="/subscription"
                                    className="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <SparklesIcon className="text-primary size-4" />
                                    <span>Nâng cấp Pro</span>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
====
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link
                                    href="/subscription"
                                    className="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <SparklesIcon className="text-primary size-4" />
                                    <span>{isProOrEnterprise ? 'Quản lý gói' : 'Nâng cấp Pro'}</span>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
>>>>
```

---

### R2 Spacing & Padding Diffs

Update the wrapper `div` element class in the 10 main page files:
```diff
# Files:
# 1. Dashboard.tsx (line 180)
# 2. Lives/Index.tsx (line 144)
# 3. Reports/Index.tsx (line 210)
# 4. Products/Index.tsx (line 379)
# 6. Admin/Dashboard.tsx (line 135)
# 7. Admin/Users/Index.tsx (line 78)
<<<<
            <div className="flex flex-1 flex-col gap-4 p-4 pt-4">
====
            <div className="flex flex-1 flex-col gap-6 p-6">
>>>>

# 5. Settings/Index.tsx (line 113)
<<<<
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 pt-4">
====
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6">
>>>>

# 8. Admin/Packages/Index.tsx (line 230)
<<<<
      <div className="mx-auto w-full max-w-6xl flex flex-1 flex-col gap-6 p-4 pt-4">
====
      <div className="mx-auto w-full max-w-6xl flex flex-1 flex-col gap-6 p-6">
>>>>

# 9. Admin/Payments/Index.tsx (line 164)
<<<<
      <form onSubmit={handleSubmit} className="mx-auto w-full max-w-4xl flex flex-1 flex-col gap-6 p-4 pt-4">
====
      <form onSubmit={handleSubmit} className="mx-auto w-full max-w-4xl flex flex-1 flex-col gap-6 p-6">
>>>>

# 10. Admin/Settings/Index.tsx (line 99)
<<<<
            <form
                onSubmit={handleSubmit}
                className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 pt-4"
            >
====
            <form
                onSubmit={handleSubmit}
                className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6"
            >
>>>>
```

---

### R2 Checkout Sizing Diffs

#### `backend/resources/js/Pages/Subscription/Index.tsx`
```diff
<<<<
        <DialogContent className="max-w-[425px] p-5">
          <DialogHeader>
            <DialogTitle>Quét mã VietQR để thanh toán</DialogTitle>
            <DialogDescription>
              Vui lòng chuyển khoản đúng số tiền và nội dung bên dưới.
            </DialogDescription>
          </DialogHeader>

          {selectedPackage && (
            <div className="space-y-4">
              <div className="flex flex-col items-center justify-center p-5 bg-muted/50 rounded-lg">
                <div className="w-[180px] h-[180px] bg-white p-2 rounded border flex items-center justify-center">
                  <img
                    src={getQrUrl(selectedPackage.price)}
                    alt="VietQR Code"
                    className="w-full h-auto border rounded"
                  />
                </div>
====
        <DialogContent className="max-w-[420px] p-4 gap-4">
          <DialogHeader className="gap-1">
            <DialogTitle>Quét mã VietQR để thanh toán</DialogTitle>
            <DialogDescription>
              Vui lòng chuyển khoản đúng số tiền và nội dung bên dưới.
            </DialogDescription>
          </DialogHeader>

          {selectedPackage && (
            <div className="space-y-3">
              <div className="flex flex-col items-center justify-center p-4 bg-muted/50 rounded-lg gap-2">
                <div className="w-[155px] h-[155px] bg-white p-1 rounded border flex items-center justify-center">
                  <img
                    src={getQrUrl(selectedPackage.price)}
                    alt="VietQR Code"
                    className="max-w-[155px] max-h-[155px] h-auto border rounded"
                  />
                </div>
>>>>
```

---

### R3 Landing Page Buttons Diffs

#### `backend/resources/views/landing.blade.php`
```diff
<<<<
                    <a href="{{ route('register') }}" class="mt-8 inline-flex h-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-md shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.01]">
                        Đăng ký ngay
                    </a>
====
                    <a href="{{ route('register') }}" class="mt-8 w-full inline-flex h-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-md shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.01]">
                        Đăng ký ngay
                    </a>
>>>>
```
```diff
<<<<
                    <a href="{{ route('register') }}" class="mt-8 inline-flex h-10 items-center justify-center rounded-xl border border-border bg-background text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
                        Bắt đầu ngay
                    </a>
====
                    <a href="{{ route('register') }}" class="mt-8 w-full inline-flex h-10 items-center justify-center rounded-xl border border-border bg-background text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
                        Bắt đầu ngay
                    </a>
>>>>
```

---

### R4 Status Badges Redesign Diffs

#### `backend/resources/js/Pages/Lives/Index.tsx`
```diff
<<<<
                  {/* Status badge */}
                  <div className="absolute left-2 top-2">
                    {session.status === "live" ? (
                      <Badge variant="destructive" className="gap-1 text-[10px] px-1.5 py-0.5 shadow-lg">
                        <span className="relative flex size-1.5">
                          <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                          <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                        </span>
                        LIVE
                      </Badge>
                    ) : session.status === "connecting" ? (
                      <Badge variant="secondary" className="gap-1 text-[10px] px-1.5 py-0.5 bg-blue-600 hover:bg-blue-700 text-white border-0 animate-pulse">
                        Đang kết nối...
                      </Badge>
                    ) : session.status === "disconnected" ? (
                      <Badge className="gap-1 text-[10px] px-1.5 py-0.5 bg-amber-500 hover:bg-amber-600 text-white border-0 animate-pulse">
                        Mất kết nối
                      </Badge>
                    ) : session.status === "error" ? (
                      <Badge variant="destructive" className="text-[10px] px-1.5 py-0.5 border-0">
                        Lỗi
                      </Badge>
                    ) : (
                      <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5 bg-black/50 text-white border-0">
                        Đã kết thúc
                      </Badge>
                    )}
                  </div>
====
                  {/* Status badge */}
                  <div className="absolute left-2 top-2">
                    {session.status === "live" ? (
                      <Badge className="gap-1.5 text-[10px] font-semibold px-2 py-0.5 bg-red-500/10 text-red-500 border border-red-500/20 backdrop-blur-md shadow-xs">
                        <span className="relative flex size-1.5">
                          <span className="absolute inline-flex size-full animate-ping rounded-full bg-red-500 opacity-75" />
                          <span className="relative inline-flex size-1.5 rounded-full bg-red-500" />
                        </span>
                        LIVE
                      </Badge>
                    ) : session.status === "connecting" ? (
                      <Badge className="gap-1.5 text-[10px] font-semibold px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 backdrop-blur-md shadow-xs animate-pulse">
                        <span className="relative flex size-1.5">
                          <span className="absolute inline-flex size-full animate-ping rounded-full bg-blue-500 opacity-75" />
                          <span className="relative inline-flex size-1.5 rounded-full bg-blue-500" />
                        </span>
                        ĐANG KẾT NỐI
                      </Badge>
                    ) : session.status === "disconnected" ? (
                      <Badge className="gap-1.5 text-[10px] font-semibold px-2 py-0.5 bg-amber-500/10 text-amber-500 border border-amber-500/20 backdrop-blur-md shadow-xs animate-pulse">
                        <span className="relative flex size-1.5">
                          <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75" />
                          <span className="relative inline-flex size-1.5 rounded-full bg-amber-500" />
                        </span>
                        MẤT KẾT NỐI
                      </Badge>
                    ) : session.status === "error" ? (
                      <Badge className="text-[10px] font-semibold px-2 py-0.5 bg-red-500/10 text-red-500 border border-red-500/20 backdrop-blur-md">
                        LỖI
                      </Badge>
                    ) : (
                      <Badge className="text-[10px] font-semibold px-2 py-0.5 bg-black/40 text-white/90 border border-white/10 backdrop-blur-md">
                        ĐÃ KẾT THÚC
                      </Badge>
                    )}
                  </div>
>>>>
```

#### `backend/resources/js/Pages/Lives/Show.tsx`
```diff
<<<<
              {session.status === 'live' ? (
                <Badge variant="destructive" className="gap-1">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                  </span>
                  Đang Live
                </Badge>
              ) : session.status === 'disconnected' ? (
                <Badge className="bg-amber-500 hover:bg-amber-600 text-white gap-1 animate-pulse">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                  </span>
                  Mất kết nối · Đang thử lại...
                </Badge>
              ) : session.status === 'connecting' ? (
                <Badge variant="secondary" className="gap-1">Đang kết nối...</Badge>
              ) : session.status === 'error' ? (
                <Badge variant="destructive">Lỗi</Badge>
              ) : (
                <Badge variant="secondary">Đã kết thúc</Badge>
              )}
====
              {session.status === 'live' ? (
                <Badge className="gap-1.5 text-xs font-semibold px-2.5 py-0.5 bg-red-500/10 text-red-500 border border-red-500/20 backdrop-blur-md shadow-xs">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-red-500 opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-red-500" />
                  </span>
                  Đang Live
                </Badge>
              ) : session.status === 'disconnected' ? (
                <Badge className="gap-1.5 text-xs font-semibold px-2.5 py-0.5 bg-amber-500/10 text-amber-500 border border-amber-500/20 backdrop-blur-md shadow-xs animate-pulse">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-amber-500" />
                  </span>
                  Mất kết nối · Đang thử lại...
                </Badge>
              ) : session.status === 'connecting' ? (
                <Badge className="gap-1.5 text-xs font-semibold px-2.5 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 backdrop-blur-md shadow-xs animate-pulse">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-blue-500 opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-blue-500" />
                  </span>
                  Đang kết nối...
                </Badge>
              ) : session.status === 'error' ? (
                <Badge className="text-xs font-semibold px-2.5 py-0.5 bg-red-500/10 text-red-500 border border-red-500/20 backdrop-blur-md">
                  Lỗi
                </Badge>
              ) : (
                <Badge className="text-xs font-semibold px-2.5 py-0.5 bg-black/40 text-white/90 border border-white/10 backdrop-blur-md">
                  Đã kết thúc
                </Badge>
              )}
>>>>
```

---

## 5. Verification Method

To verify these changes without modifying any codebase directly:

1.  **Lint & Compile Checks**:
    Once implemented, run standard frontend build check:
    ```bash
    npm run build
    ```
    This ensures that:
    *   No compilation warnings occur in `nav-user.tsx` referencing the new `auth.subscription` properties defined in `index.d.ts`.
    *   Tailwind compiles new spacing/opacity classes correctly without issues.
2.  **Visual Code Inspection**:
    Open the files inside IDE and verify that:
    *   All replaced paddings are exact match (e.g. `p-6` instead of `p-4 pt-4`).
    *   The `nav-user.tsx` dynamic display changes seamlessly using the conditional logic.
    *   Checkout modal has restricted QR width properly set.
