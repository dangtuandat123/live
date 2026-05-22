# Handoff Report — Subscription Limits Gating & Progress Color Customization

This report contains findings, design options, and exact code modification proposals for implementing subscription limits display, stream creation gating, and custom sidebar progress bar coloring.

---

## 1. Observation

### A. Location and Structure of `Setup.tsx`
- **File Path**: `backend/resources/js/Pages/Lives/Setup.tsx`
- **Imports & Types**:
  - `Badge` is imported from `@/components/ui/badge` (Line 1).
  - `Card`, `CardContent`, `CardDescription`, `CardHeader`, `CardTitle` are imported from `@/components/ui/card` (Lines 11–17).
  - Props are typed as:
    ```typescript
    interface Props {
        products: Product[];
        active_streams_count: number;
    }
    ```
  - Inertia `usePage().props` has a manually cast `auth` object on lines 52-60:
    ```typescript
    const { auth } = usePage().props as unknown as {
        auth: {
            subscription: {
                features?: {
                    limit_streams?: number;
                };
            } | null;
        };
    };
    ```
- **Gating Logic**:
  - Checks if stream limit is reached:
    ```typescript
    const limitStreams = auth?.subscription?.features?.limit_streams ?? 1;
    const isGated = limitStreams !== -1 && active_streams_count >= limitStreams;
    ```
  - Disables the submit button:
    ```typescript
    <Button
        type="submit"
        size="lg"
        disabled={form.processing || isGated}
    >
    ```
  - Displays a warning alert banner at lines 134-155:
    ```typescript
    {isGated && (
        <div className="border-destructive/20 bg-destructive/10 text-destructive rounded-lg border p-4">
            ...
        </div>
    )}
    ```

### B. Location and Structure of `app-sidebar.tsx`
- **File Path**: `backend/resources/js/Components/app-sidebar.tsx`
- **Credit Computation**:
  - Lines 52-56:
    ```typescript
    const subscription = auth?.subscription;
    const used = subscription?.used_ai_credits ?? 0;
    const limit = subscription?.features?.ai_credits ?? 1000;
    const percentage =
        limit > 0 ? Math.min(100, Math.max(0, (used / limit) * 100)) : 0;
    ```
  - Collapsed Badge render (Lines 68-72):
    ```typescript
    {limit !== -1 && (
        <div className="bg-primary text-primary-foreground border-sidebar absolute -right-1 -bottom-1 flex h-4 w-4 items-center justify-center rounded-full border text-[8px] font-bold">
            {Math.round(percentage)}%
        </div>
    )}
    ```
  - Progress bar render (Lines 120-122):
    ```typescript
    ) : (
        <Progress value={percentage} className="h-1.5" />
    )}
    ```

### C. Types Definition file `index.d.ts`
- **File Path**: `backend/resources/js/types/index.d.ts`
- **Features Typed**:
  ```typescript
  export interface UserSubscriptionFeatures {
      limit_streams?: number;
      max_duration_hours?: number;
      ai_credits?: number;
      audio_analysis?: boolean;
      export_leads?: boolean;
  }
  ```
- **Page Props Typed**:
  ```typescript
  export type PageProps<
      T extends Record<string, unknown> = Record<string, unknown>,
  > = T & {
      auth: {
          user: User;
          subscription: UserSubscription | null;
      };
  };
  ```

### D. Reusable `Progress` Component
- **File Path**: `backend/resources/js/Components/ui/progress.tsx`
- **Indicator Element**:
  ```typescript
  <ProgressPrimitive.Indicator
      data-slot="progress-indicator"
      className="bg-primary size-full flex-1 transition-all"
      style={{ transform: `translateX(-${100 - (value || 0)}%)` }}
  />
  ```

---

## 2. Logic Chain

1. **Shared State Access**: The global `usePage<PageProps>()` hook resolves all necessary subscription properties (e.g. `used_ai_credits`, `active_streams_count`) and features (e.g. `limit_streams`, `max_duration_hours`, `ai_credits`, `audio_analysis`, `export_leads`).
2. **Displaying Subscription limits info card on Setup.tsx**:
   - We can add a Card component under the main header on `Setup.tsx`.
   - The card will read properties from `auth.subscription` and display them side-by-side using a grid (`grid-cols-1 md:grid-cols-2 lg:grid-cols-4`).
   - We will display support statuses for premium features (`audio_analysis`, `export_leads`) with check marks (emerald badges) for active features and lock icons (muted badges) for locked features.
3. **Lock/disable the stream creation submit button & Upgrade instructions**:
   - The submit button is already disabled by `isGated`.
   - To make upgrade instructions actionable, we will add a CTA button inside the warning alert banner redirecting the user to `route('subscription.index')` (the billing/plans catalog) and a helper link to navigate to the active streams list (`route('lives.index')`).
4. **Color Progress Bar according to credit thresholds**:
   - We calculate credit usage percentage.
   - In `app-sidebar.tsx`, under 10% remaining means `percentage >= 90` (red indicator). Between 10% and 20% remaining means `percentage >= 80` (amber indicator).
   - In `progress.tsx`, `<ProgressPrimitive.Indicator>` currently hardcodes `bg-primary`. If we add an `indicatorClassName` prop to the `Progress` interface, callers like `app-sidebar.tsx` can dynamically control the indicator color class (e.g., `bg-destructive`, `bg-amber-500`, or `bg-primary`).

---

## 3. Caveats

- **Active Stream Counting**: The active stream count `active_streams_count` is passed down both as a page prop on creation and is synchronized globally under `auth.subscription.active_streams_count` via the Inertia request lifecycle. The code should safely fall back from `auth.subscription.active_streams_count` to `active_streams_count` (prop) to ensure correctness under both initial render and subsequent Inertia page reloads.
- **Radix UI Primitive element**: Radix UI's `ProgressPrimitive.Indicator` renders as a `div` element inside the DOM. Using an explicit `indicatorClassName` prop is safer than writing deep-nested Tailwind classes (`[&>div]:bg-red-500`), which could break if Radix UI versions are changed or if DOM structure changes.

---

## 4. Conclusion

We propose the following clean, localized modifications to satisfy the requirements:

### Target A: Reusable Progress Component (`backend/resources/js/Components/ui/progress.tsx`)
Extend `Progress` component to support the `indicatorClassName` prop.

```tsx
import { Progress as ProgressPrimitive } from 'radix-ui';
import * as React from 'react';

import { cn } from '@/lib/utils';

// Declare custom props interface extending Radix UI component props
interface ProgressProps extends React.ComponentProps<typeof ProgressPrimitive.Root> {
    indicatorClassName?: string;
}

function Progress({
    className,
    indicatorClassName,
    value,
    ...props
}: ProgressProps) {
    return (
        <ProgressPrimitive.Root
            data-slot="progress"
            className={cn(
                'bg-muted relative flex h-1 w-full items-center overflow-x-hidden rounded-full',
                className,
            )}
            {...props}
        >
            <ProgressPrimitive.Indicator
                data-slot="progress-indicator"
                className={cn(
                    'bg-primary size-full flex-1 transition-all',
                    indicatorClassName
                )}
                style={{ transform: `translateX(-${100 - (value || 0)}%)` }}
            />
        </ProgressPrimitive.Root>
    );
}

export { Progress };
```

### Target B: Sidebar Credits (`backend/resources/js/Components/app-sidebar.tsx`)
Apply dynamic coloring to the credits progress bar and collapsed progress badge.

```tsx
function SidebarCredits() {
    const { auth } = usePage<PageProps>().props;
    const { state, isMobile } = useSidebar();

    React.useEffect(() => {
        if (!auth?.user) return;

        const interval = setInterval(() => {
            router.reload({
                only: ['auth', 'activeSubscription'],
            });
        }, 10000);

        return () => clearInterval(interval);
    }, [auth?.user]);

    if (!auth?.user) return null;

    const subscription = auth?.subscription;
    const used = subscription?.used_ai_credits ?? 0;
    const limit = subscription?.features?.ai_credits ?? 1000;
    const percentage =
        limit > 0 ? Math.min(100, Math.max(0, (used / limit) * 100)) : 0;

    // Define colors dynamically based on percentage used
    const isCritical = limit !== -1 && percentage >= 90;
    const isWarning = limit !== -1 && percentage >= 80 && percentage < 90;

    const progressIndicatorClass = isCritical
        ? 'bg-destructive'
        : isWarning
        ? 'bg-amber-500'
        : 'bg-primary';

    const collapsedBadgeClass = isCritical
        ? 'bg-destructive text-destructive-foreground'
        : isWarning
        ? 'bg-amber-500 text-white'
        : 'bg-primary text-primary-foreground';

    if (state === 'collapsed' && !isMobile) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Link
                        href="/subscription"
                        className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground group relative mx-auto flex h-9 w-9 items-center justify-center rounded-lg transition-colors"
                    >
                        <div className="relative flex items-center justify-center">
                            <SparklesIcon className="text-primary size-5 animate-pulse" />
                            {limit !== -1 && (
                                <div className={cn(
                                    "border-sidebar absolute -right-1 -bottom-1 flex h-4 w-4 items-center justify-center rounded-full border text-[8px] font-bold transition-colors",
                                    collapsedBadgeClass
                                )}>
                                    {Math.round(percentage)}%
                                </div>
                            )}
                        </div>
                    </Link>
                </TooltipTrigger>
                <TooltipContent
                    side="right"
                    align="center"
                    className="bg-sidebar border-sidebar-border space-y-1 border p-2 shadow-lg"
                >
                    <p className="text-sidebar-foreground text-xs font-semibold">
                        AI Credits
                    </p>
                    <p className="text-sidebar-foreground/70 text-[11px]">
                        {used.toLocaleString()} /{' '}
                        {limit === -1 ? 'Vô hạn' : limit.toLocaleString()}
                    </p>
                </TooltipContent>
            </Tooltip>
        );
    }

    return (
        <div className="border-sidebar-border/50 bg-sidebar-accent/10 mx-2 my-2 space-y-2 rounded-lg border p-3 backdrop-blur-md">
            <div className="flex items-center justify-between text-xs">
                <div className="text-sidebar-foreground/80 flex items-center gap-1.5 font-medium">
                    <SparklesIcon className="text-primary size-3.5 animate-pulse" />
                    <span>AI Credits</span>
                </div>
                <Link
                    href="/subscription"
                    className="text-primary hover:text-primary/80 text-[10px] font-bold tracking-wider uppercase transition-colors"
                >
                    Nâng cấp
                </Link>
            </div>

            <div className="space-y-1">
                <div className="text-sidebar-foreground/80 flex justify-between text-[10px] font-semibold">
                    <span className="text-sidebar-foreground/50">Đã dùng</span>
                    <span>
                        {used.toLocaleString()} /{' '}
                        {limit === -1 ? 'Vô hạn' : limit.toLocaleString()}
                    </span>
                </div>
                {limit === -1 ? (
                    <div className="bg-primary/20 h-1.5 w-full overflow-hidden rounded-full">
                        <div className="from-primary h-full w-full animate-pulse bg-gradient-to-r via-purple-500 to-indigo-500" />
                    </div>
                ) : (
                    <Progress 
                        value={percentage} 
                        className="h-1.5" 
                        indicatorClassName={progressIndicatorClass} 
                    />
                )}
            </div>
        </div>
    );
}
```

### Target C: Setup View (`backend/resources/js/Pages/Lives/Setup.tsx`)
Add the subscription limits info card and upgrade CTA.

```tsx
// Insert these imports at the top
import { Progress } from '@/components/ui/progress';
import type { PageProps } from '@/types';
import { 
    LoaderIcon, 
    VideoIcon, 
    XIcon, 
    ClockIcon, 
    SparklesIcon, 
    CheckCircle2Icon, 
    LockIcon 
} from 'lucide-react';

// Within function LivesSetup
export default function LivesSetup({
    products,
    active_streams_count = 0,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const subscription = auth?.subscription;
    const features = subscription?.features;

    const limitStreams = features?.limit_streams ?? 1;
    const activeStreamsUsed = subscription?.active_streams_count ?? active_streams_count;
    const isGated = limitStreams !== -1 && activeStreamsUsed >= limitStreams;

    const maxDurationHours = features?.max_duration_hours ?? 1;
    const aiCredits = features?.ai_credits ?? 1000;
    const usedAiCredits = subscription?.used_ai_credits ?? 0;
    const audioAnalysis = features?.audio_analysis ?? false;
    const exportLeads = features?.export_leads ?? false;

    // Remaining logic...
```

#### Card render layout proposal:
Insert this card right under the screen title block (Line 133):

```tsx
                {/* Subscription Limits Info Card */}
                <Card className="border-primary/20 bg-primary/5">
                    <CardHeader className="pb-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold flex items-center gap-2">
                                    <SparklesIcon className="h-5 w-5 text-primary" />
                                    Thông tin gói cước & Giới hạn
                                </CardTitle>
                                <CardDescription>
                                    Chi tiết về gói cước đang sử dụng và các giới hạn đi kèm.
                                </CardDescription>
                            </div>
                            <Badge variant="default" className="text-xs px-2.5 py-0.5 font-semibold">
                                Gói {subscription?.package_name ?? 'Free'}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {/* Concurrent Streams */}
                            <div className="bg-background border-border/50 rounded-xl border p-3 flex flex-col justify-between">
                                <div className="flex items-center gap-2 text-muted-foreground text-xs font-medium mb-1">
                                    <VideoIcon className="h-4 w-4 text-sky-500" />
                                    <span>Luồng live đồng thời</span>
                                </div>
                                <div>
                                    <div className={`text-xl font-bold ${isGated ? 'text-destructive' : 'text-foreground'}`}>
                                        {activeStreamsUsed} / {limitStreams === -1 ? 'Không giới hạn' : limitStreams}
                                    </div>
                                    <span className="text-[10px] text-muted-foreground">
                                        Số luồng đang chạy đồng thời
                                    </span>
                                </div>
                            </div>

                            {/* Max Duration */}
                            <div className="bg-background border-border/50 rounded-xl border p-3 flex flex-col justify-between">
                                <div className="flex items-center gap-2 text-muted-foreground text-xs font-medium mb-1">
                                    <ClockIcon className="h-4 w-4 text-emerald-500" />
                                    <span>Thời lượng live tối đa</span>
                                </div>
                                <div>
                                    <div className="text-xl font-bold text-foreground">
                                        {maxDurationHours === -1 ? 'Không giới hạn' : `${maxDurationHours} giờ`}
                                    </div>
                                    <span className="text-[10px] text-muted-foreground">
                                        Thời lượng tối đa mỗi phiên live
                                    </span>
                                </div>
                            </div>

                            {/* AI Credits */}
                            <div className="bg-background border-border/50 rounded-xl border p-3 flex flex-col justify-between">
                                <div className="flex items-center gap-2 text-muted-foreground text-xs font-medium mb-1">
                                    <SparklesIcon className="h-4 w-4 text-purple-500" />
                                    <span>Tín dụng AI (Credits)</span>
                                </div>
                                <div>
                                    <div className="text-xl font-bold text-foreground">
                                        {usedAiCredits.toLocaleString()} / {aiCredits === -1 ? 'Vô hạn' : aiCredits.toLocaleString()}
                                    </div>
                                    {aiCredits !== -1 && (
                                        <div className="mt-1">
                                            <Progress 
                                                value={Math.min(100, Math.max(0, (usedAiCredits / aiCredits) * 100))} 
                                                className="h-1" 
                                                indicatorClassName={
                                                    (usedAiCredits / aiCredits) >= 0.9 
                                                        ? 'bg-destructive' 
                                                        : (usedAiCredits / aiCredits) >= 0.8 
                                                            ? 'bg-amber-500' 
                                                            : 'bg-primary'
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Premium Features Status */}
                            <div className="bg-background border-border/50 rounded-xl border p-3 flex flex-col justify-between">
                                <span className="text-muted-foreground text-xs font-medium mb-2 block">
                                    Tính năng cao cấp
                                </span>
                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="text-muted-foreground text-sm">Phân tích âm thanh</span>
                                        {audioAnalysis ? (
                                            <Badge variant="outline" className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600 text-[10px] px-1.5 py-0">
                                                <CheckCircle2Icon className="mr-0.5 h-3 w-3 inline" />
                                                Hỗ trợ
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="border-muted bg-muted text-muted-foreground text-[10px] px-1.5 py-0">
                                                <LockIcon className="mr-0.5 h-2.5 w-2.5 inline" />
                                                Khóa
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="text-muted-foreground text-sm">Xuất dữ liệu CSV</span>
                                        {exportLeads ? (
                                            <Badge variant="outline" className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600 text-[10px] px-1.5 py-0">
                                                <CheckCircle2Icon className="mr-0.5 h-3 w-3 inline" />
                                                Hỗ trợ
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="border-muted bg-muted text-muted-foreground text-[10px] px-1.5 py-0">
                                                <LockIcon className="mr-0.5 h-2.5 w-2.5 inline" />
                                                Khóa
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
```

#### Upgrade Instructions CTA logic:
Modify the existing `isGated` banner check to incorporate the upgrade CTA:

```tsx
                {isGated && (
                    <div className="border-destructive/20 bg-destructive/10 text-destructive rounded-lg border p-4">
                        <div className="flex items-center gap-3">
                            <div className="bg-destructive/20 shrink-0 rounded-full p-2">
                                <XIcon className="text-destructive h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h4 className="text-sm font-semibold">
                                    Đã đạt giới hạn số lượng livestream hoạt động
                                </h4>
                                <p className="text-destructive/80 mt-1 text-xs">
                                    Gói đăng ký hiện tại của bạn giới hạn tối đa{' '}
                                    {limitStreams} phiên livestream hoạt động đồng thời. 
                                    Vui lòng kết thúc một phiên livestream hiện tại trước khi tạo mới hoặc nâng cấp gói dịch vụ.
                                </p>
                                <div className="mt-3 flex items-center gap-2">
                                    <Button variant="destructive" size="sm" asChild>
                                        <Link href={route('subscription.index')}>
                                            Nâng cấp gói ngay
                                        </Link>
                                    </Button>
                                    <span className="text-xs text-destructive/70">
                                        hoặc kết thúc livestream đang hoạt động tại{' '}
                                        <Link href={route('lives.index')} className="underline hover:text-destructive font-semibold">
                                            Danh sách phiên live
                                        </Link>.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
```

---

## 5. Verification Method

To verify these changes:
1. **Types Integration Verification**:
   - Run Vite/TypeScript compiler to verify that no compilation issues exist:
     ```powershell
     npm run build
     ```
2. **Setup View Verification (Statically)**:
   - Verify that all components (`Card`, `CardContent`, `CardDescription`, `CardHeader`, `CardTitle`, `Badge`, `Progress`, `Link`, and `Button`) are imported and resolve correctly inside `Setup.tsx`.
   - Verify that icons are correctly resolved from `lucide-react`.
3. **Setup View Verification (Dynamically/Interactively)**:
   - Simulate changing `active_streams_count` parameter to exceed `limit_streams`. The warning banner must show the "Nâng cấp gói ngay" button, and the submit button must be disabled and display "Đã đạt giới hạn gói".
   - Under standard limits, the limits card must show the current limits dynamically with active features showing emerald active badges and locked features showing muted badges with lock icons.
4. **Sidebar Credit Verification**:
   - Change `used_ai_credits` in the user's subscription to values representing:
     - 50% usage: Progress bar is blue/primary, collapsed badge is blue/primary.
     - 82% usage: Progress bar turns amber, collapsed badge turns amber.
     - 95% usage: Progress bar turns red (`bg-destructive`), collapsed badge turns red.
