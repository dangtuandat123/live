# Handoff Report — Explorer Index Backend Analysis

## 1. Observation

### Exact File Paths and Code Blocks Involved
1. **Inertia Share Hook & Shared Data Structure (`backend/app/Http/Middleware/HandleInertiaRequests.php`):**
   Lines 53-73 define the subscription properties sent under `auth.subscription`:
   ```php
   $subscription = [
       'active' => (bool) $activeSub?->isActive(),
       'package_id' => $activeSub?->subscription_package_id,
       'package_name' => $activeSub?->package?->name ?? 'Free',
       'price' => $activeSub?->package?->price ?? 0,
       'duration_days' => $activeSub?->package?->duration_days ?? 30,
       'expires_at' => $activeSub?->expires_at?->toISOString(),
       'used_ai_credits' => $activeSub?->used_ai_credits ?? 0,
       'active_streams_count' => $activeStreamsCount,
       'total_sessions_in_cycle' => $totalSessionsInCycle,
       'features' => $user->getSubscriptionFeatures(),
   ];
   ...
   return [
       ...parent::share($request),
       'auth' => [
           'user' => $user,
           'subscription' => $subscription,
       ],
   ];
   ```

2. **Session Listing Controller API (`backend/app/Http/Controllers/LiveSessionController.php`):**
   Lines 54-69 map the `LiveSession` model for the listing view:
   ```php
   $sessions = $query->paginate(12)->through(function (LiveSession $session) {
       return [
           'id' => $session->id,
           'name' => $session->name,
           'status' => $session->status,
           'comments' => $session->stats?->total_comments ?? $session->comments_count ?? 0,
           'views' => $session->stats?->total_views ?? 0,
           'viewer_count' => $session->stats?->viewer_count ?? 0,
           'leads' => $session->stats?->leads_count ?? 0,
           'sentiment' => LiveStat::sentimentScore($session->stats),
           'duration' => $session->duration_formatted,
           'products' => $session->products_count ?? 0,
           'date' => $session->created_at?->format('d/m/Y') ?? '',
           'thumbnail' => $session->thumbnail,
       ];
   });
   ```
   *Note:* The `error_message` attribute is currently omitted from this response.

3. **Recent Sessions on Dashboard (`backend/app/Http/Controllers/DashboardController.php`):**
   Lines 226-236 map the `LiveSession` model for the dashboard view:
   ```php
   $recentSessions = LiveSession::forUser($userId)
       ->with('stats')
       ->withCount(['events as comments_count' => fn ($q) => $q->where('event_type', 'comment')])
       ->orderByDesc('created_at')
       ->limit(5)
       ->get()
       ->map(fn (LiveSession $session) => [
           'id' => (string) $session->id,
           'name' => $session->name,
           'status' => $session->status,
           'comments' => $session->stats?->total_comments ?? $session->comments_count ?? 0,
           'views' => $session->stats?->total_views ?? 0,
           'leads' => $session->stats?->leads_count ?? 0,
           'sentiment' => LiveStat::sentimentScore($session->stats),
           'duration' => $session->duration_formatted,
           'date' => $session->created_at?->format('d/m/Y') ?? '',
       ])->toArray();
   ```
   *Note:* The `error_message` attribute is also omitted from this response.

4. **Live Session Status Badges in Lives Listing (`backend/resources/js/Pages/Lives/Index.tsx`):**
   Lines 296-330 manage status rendering in listing card overlays:
   ```tsx
   {/* Status badge */}
   <div className="absolute top-2 left-2">
       {session.status === 'live' ? (
           <Badge className="...">Đang phát trực tiếp</Badge>
       ) : session.status === 'connecting' ? (
           <Badge className="...">Đang kết nối</Badge>
       ) : session.status === 'disconnected' ? (
           <Badge className="...">MẤT KẾT NỐI</Badge>
       ) : session.status === 'error' ? (
           <Badge className="...">LỖI</Badge>
       ) : (
           <Badge className="...">ĐÃ KẾT THÚC</Badge>
       )}
   </div>
   ```

5. **Live Session Status Badges in Dashboard (`backend/resources/js/Pages/Dashboard.tsx`):**
   Lines 120-133 define the `StatusBadge` helper component:
   ```tsx
   function StatusBadge({ status }: { status: string }) {
       if (status === 'live') {
           return (
               <Badge variant="destructive" className="gap-1">
                   ...
                   Đang Live
               </Badge>
           );
       }
       return <Badge variant="secondary">Đã kết thúc</Badge>;
   }
   ```

6. **Test Expectations (`backend/tests/Feature/SubscriptionGatingTest.php`):**
   - Stream duration limit gating sets status to `'ended'` and puts `'vượt quá thời lượng tối đa cho phép'` in `error_message` (lines 113-115).
   - AI credits limit gating sets status to `'error'` and puts `'Đã hết tín dụng AI của gói dịch vụ.'` in `error_message` (lines 169-170).

---

## 2. Logic Chain

1. **Information Delivery:**
   To render a specialized status badge when a livestream is stopped due to limit, the frontend needs access to the `error_message` of the `LiveSession`. However, the controllers mapping sessions for listing (`LiveSessionController::index`) and the dashboard (`DashboardController::index`) do not include `error_message`.
2. **Identification of stopped reason:**
   - **Duration Limit Stop:** The backend sets status to `'ended'` and writes a string containing `vượt quá thời lượng tối đa` into `error_message`.
   - **AI Credits Limit Stop:** The backend sets status to `'error'` and writes `'Đã hết tín dụng AI của gói dịch vụ.'` into `error_message`.
3. **Trigger condition on frontend:**
   By matching these substrings from `error_message` inside React components, we can conditionalize the badges:
   - Status `'ended'` + `error_message` matching `'thời lượng tối đa'` or `'max duration'` => **"Bị ngắt (Hết giờ)"**
   - Status `'error'` + `error_message` matching `'tín dụng AI'` or `'ai credits'` => **"Đạt giới hạn"**

---

## 3. Caveats

- We assumed that there are no translation/localization layers changing these raw messages on the DB level. If a multi-language setup is introduced, checking raw strings like `"tín dụng AI"` might fail. A more robust solution would store limit error types in a separate enum/column (e.g. `stop_reason = 'duration_limit' | 'credits_limit'`), but editing database schemas is out of scope.
- We did not investigate third-party packages or frontends outside of Inertia, as this project runs entirely on Laravel and React+Inertia.

---

## 4. Conclusion & Implementation Plan

### Propose backend changes (Diff patch details):
Add `'error_message' => $session->error_message` to the mapped items.

**Patch for `backend/app/Http/Controllers/LiveSessionController.php`:**
```diff
@@ -65,3 +65,4 @@
                 'products' => $session->products_count ?? 0,
                 'date' => $session->created_at?->format('d/m/Y') ?? '',
                 'thumbnail' => $session->thumbnail,
+                'error_message' => $session->error_message,
             ];
```

**Patch for `backend/app/Http/Controllers/DashboardController.php`:**
```diff
@@ -234,3 +234,4 @@
                 'duration' => $session->duration_formatted,
                 'date' => $session->created_at?->format('d/m/Y') ?? '',
+                'error_message' => $session->error_message,
             ])->toArray();
```

### Propose frontend changes:

**For `backend/resources/js/Pages/Lives/Index.tsx`:**
1. Update `Session` interface to include `error_message?: string | null`.
2. Update the status badge render block:
```tsx
{session.status === 'live' ? (
    <Badge className="bg-destructive text-destructive-foreground gap-1.5 px-2 py-0.5 text-[10px] font-semibold shadow-xs">
        <span className="relative flex size-1.5">
            <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75" />
            <span className="relative inline-flex size-1.5 rounded-full bg-white" />
        </span>
        Đang phát trực tiếp
    </Badge>
) : session.status === 'connecting' ? (
    <Badge className="bg-primary text-primary-foreground gap-1.5 px-2 py-0.5 text-[10px] font-semibold shadow-xs">
        <span className="relative flex size-1.5">
            <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75" />
            <span className="relative inline-flex size-1.5 rounded-full bg-white" />
        </span>
        Đang kết nối
    </Badge>
) : session.status === 'disconnected' ? (
    <Badge className="animate-pulse gap-1.5 border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-500 shadow-xs backdrop-blur-md">
        <span className="relative flex size-1.5">
            <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75" />
            <span className="relative inline-flex size-1.5 rounded-full bg-amber-500" />
        </span>
        MẤT KẾT NỐI
    </Badge>
) : session.status === 'error' ? (
    session.error_message?.toLowerCase().includes('tín dụng') ? (
        <Badge className="border border-red-500/20 bg-red-500/10 px-2 py-0.5 text-[10px] font-semibold text-red-500 backdrop-blur-md">
            Đạt giới hạn
        </Badge>
    ) : (
        <Badge className="border border-red-500/20 bg-red-500/10 px-2 py-0.5 text-[10px] font-semibold text-red-500 backdrop-blur-md">
            LỖI
        </Badge>
    )
) : (
    session.error_message?.toLowerCase().includes('thời lượng') ? (
        <Badge className="border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-500 backdrop-blur-md">
            Bị ngắt (Hết giờ)
        </Badge>
    ) : (
        <Badge className="border border-white/10 bg-black/40 px-2 py-0.5 text-[10px] font-semibold text-white/90 backdrop-blur-md">
            ĐÃ KẾT THÚC
        </Badge>
    )
)}
```

**For `backend/resources/js/Pages/Dashboard.tsx`:**
1. Update `RecentSessionItem` interface to include `error_message?: string | null`.
2. Update the `StatusBadge` component signature and body:
```tsx
function StatusBadge({ status, errorMessage }: { status: string; errorMessage?: string | null }) {
    if (status === 'live') {
        return (
            <Badge variant="destructive" className="gap-1">
                <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                </span>
                Đang Live
            </Badge>
        );
    }
    if (status === 'connecting') {
        return (
            <Badge variant="default" className="gap-1">
                <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-white" />
                </span>
                Đang kết nối
            </Badge>
        );
    }
    if (status === 'disconnected') {
        return (
            <Badge variant="outline" className="animate-pulse border-amber-500/20 bg-amber-500/10 text-amber-500">
                Mất kết nối
            </Badge>
        );
    }
    if (status === 'error') {
        if (errorMessage?.toLowerCase().includes('tín dụng')) {
            return (
                <Badge variant="outline" className="border-red-500/20 bg-red-500/10 text-red-500">
                    Đạt giới hạn
                </Badge>
            );
        }
        return (
            <Badge variant="outline" className="border-red-500/20 bg-red-500/10 text-red-500">
                Lỗi
            </Badge>
        );
    }
    if (status === 'ended' || status === 'finished') {
        if (errorMessage?.toLowerCase().includes('thời lượng')) {
            return (
                <Badge variant="outline" className="border-amber-500/20 bg-amber-500/10 text-amber-500">
                    Bị ngắt (Hết giờ)
                </Badge>
            );
        }
    }
    return <Badge variant="secondary">Đã kết thúc</Badge>;
}
```
3. Update the component call inside `Dashboard` rendering loop:
```tsx
<StatusBadge status={session.status} errorMessage={session.error_message} />
```

---

## 5. Verification Method

To independently verify the changes after implementation:
1. Run backend tests to ensure no regressions:
   `php artisan test --filter=SubscriptionGatingTest`
2. Create test entries in the database to simulate limit stops:
   - Run `php artisan tinker`:
     ```php
     // Create user
     $user = \App\Models\User::first();
     
     // Create a duration limit ended session
     \App\Models\LiveSession::create([
         'user_id' => $user->id,
         'name' => 'Test Duration Stopped Session',
         'tiktok_username' => 'test_user',
         'status' => 'ended',
         'error_message' => 'Phiên livestream đã tự động kết thúc do vượt quá thời lượng tối đa cho phép của gói dịch vụ (1 giờ).'
     ]);

     // Create a credits limit error session
     \App\Models\LiveSession::create([
         'user_id' => $user->id,
         'name' => 'Test Credits Stopped Session',
         'tiktok_username' => 'test_user2',
         'status' => 'error',
         'error_message' => 'Đã hết tín dụng AI của gói dịch vụ.'
     ]);
     ```
3. Log in to the web interface and navigate to the dashboard (`/dashboard`) and livestream list (`/lives`).
4. Inspect the status badges on the session list and the dashboard table. Ensure "Test Duration Stopped Session" has the badge **"Bị ngắt (Hết giờ)"** and "Test Credits Stopped Session" has the badge **"Đạt giới hạn"**.
