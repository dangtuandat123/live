# Handoff Report - UI Gating & Subscription Validation

## 1. Observation
I have inspected the backend and frontend directories to identify files involved in R4 and R5:

### 1.1 active_streams gating (R4)
- **`backend/app/Http/Controllers/LiveSessionController.php`** (lines 98-107): The setup form view is rendered in the `create` method without the user's active stream count. Only products are passed:
  ```php
  public function create(Request $request)
  {
      $products = Product::where('user_id', $request->user()->id)
          ->orderBy('name')
          ->get(['id', 'name', 'sku', 'price']);

      return Inertia::render('Lives/Setup', [
          'products' => $products,
      ]);
  }
  ```
- **`backend/resources/js/Pages/Lives/Setup.tsx`** (lines 42, 72, 255-267): The component currently receives only `products` as props. The submit button is only disabled when `form.processing` is true:
  ```tsx
  export default function LivesSetup({ products }: Props) {
      ...
      function handleSubmit(e: React.FormEvent) {
        e.preventDefault()
        form.post(route("lives.store"))
      }
      ...
      <Button type="submit" size="lg" disabled={form.processing}>
  ```
- **`backend/app/Http/Middleware/HandleInertiaRequests.php`** (line 49): Inertia shares subscription information globally, including subscription features:
  ```php
  'features' => $user->getSubscriptionFeatures(),
  ```

### 1.2 Package features validation (R5)
- **`backend/routes/web.php`** (lines 219-243): The package management routes are implemented as inline closures rather than controller methods:
  ```php
  Route::post('/packages', function (Request $request) {
      $validated = $request->validate([
          'name' => ['required', 'string', 'max:255'],
          'price' => ['required', 'integer', 'min:0'],
          'duration_days' => ['required', 'integer', 'min:1'],
          'features' => ['nullable', 'array'],
      ]);
      ...
  });
  ```
- **`backend/app/Http/Controllers/SubscriptionController.php`**: The file currently has no package CRUD methods (`store`, `update`, `destroy`), only checkout-related actions.
- **`backend/resources/js/Pages/Admin/Packages/Index.tsx`**:
  - The input limits for package features are restricted in the create/edit modals:
    - Line 418/560: limit_streams has `min="1"`
    - Line 447/592: ai_credits has `min="0"`
  - Package display badges do not yet handle `-1` as "Vô hạn":
    - Line 297: `Stream: {(pkg.features as PackageFeatures).limit_streams} luồng`
    - Line 307: `Credits: {(pkg.features as PackageFeatures).ai_credits?.toLocaleString()}`

---

## 2. Logic Chain
1. To enforce R4 client-side gating, the frontend needs the number of active streams. We can query `LiveSession::forUser($request->user()->id)->whereIn('status', ['connecting', 'live'])->count()` in `LiveSessionController@create` and send it as `active_streams_count` to `Lives/Setup.tsx`.
2. In `Setup.tsx`, using Inertia's `usePage()` hook, we can retrieve `auth.subscription.features.limit_streams` which contains the stream limit configured for the user's active subscription package.
3. If `limit_streams !== -1` and `active_streams_count >= limit_streams`, the user has reached their subscription limit.
4. If the limit is exceeded, we can import `Alert`, `AlertTitle`, and `AlertDescription` from `@/components/ui/alert`, rendering a descriptive warning alert, and disable the form submission button (`disabled={form.processing || limitExceeded}`).
5. For R5, moving the closures from `routes/web.php` to new controller methods in `SubscriptionController.php` (e.g. `storePackage` and `updatePackage`) is the most structured architectural design to manage package validation.
6. Validation rules in `SubscriptionController.php` should explicitly validate features keys, lowering the minimum value constraint to `-1` (representing unlimited):
   ```php
   'features.limit_streams' => ['nullable', 'integer', 'min:-1'],
   'features.ai_credits' => ['nullable', 'integer', 'min:-1'],
   ```
7. To support this on the frontend, update `Packages/Index.tsx` to set `min="-1"` for these fields in both create and edit forms, and render "Vô hạn" in badges if their values are `-1`.

---

## 3. Caveats
- Modifying package CRUD routing in `routes/web.php` requires replacing closure routes with calls to `SubscriptionController` methods.
- We assume `-1` represents unlimited streams and AI credits throughout the application. This is validated by backend logic checking `limitStreams !== -1` in `LiveSessionController` and `aiCreditsLimit !== -1` in `AnalyzeCommentsJob`.

---

## 4. Conclusion
The implementation plan is sound and follows existing project standards. The proposed changes are scoped and safe:

### Proposed Diff Patch: `backend/app/Http/Controllers/LiveSessionController.php`
```diff
@@ -98,6 +98,9 @@
     public function create(Request $request)
     {
         $products = Product::where('user_id', $request->user()->id)
             ->orderBy('name')
             ->get(['id', 'name', 'sku', 'price']);
 
+        $activeStreamsCount = LiveSession::forUser($request->user()->id)
+            ->whereIn('status', ['connecting', 'live'])
+            ->count();
+
         return Inertia::render('Lives/Setup', [
             'products' => $products,
+            'active_streams_count' => $activeStreamsCount,
         ]);
     }
```

### Proposed Diff Patch: `backend/resources/js/Pages/Lives/Setup.tsx`
```diff
@@ -2,2 +2,2 @@
-import { Head, useForm } from "@inertiajs/react"
+import { Head, useForm, usePage } from "@inertiajs/react"
@@ -27,2 +27,3 @@
 import { XIcon, VideoIcon, LoaderIcon } from "lucide-react"
+import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
+import { AlertTriangle } from "lucide-react"
 import * as React from "react"
@@ -39,2 +40,3 @@
 interface Props {
   products: Product[]
+  active_streams_count: number
 }
@@ -42,3 +44,8 @@
-export default function LivesSetup({ products }: Props) {
+export default function LivesSetup({ products, active_streams_count }: Props) {
+  const { auth } = usePage<{ auth: any }>().props
+  
+  const limitStreams = auth?.subscription?.features?.limit_streams ?? 1
+  const limitExceeded = limitStreams !== -1 && active_streams_count >= limitStreams
+
   const form = useForm({
     name: "",
@@ -254,4 +261,14 @@
+        {limitExceeded && (
+          <Alert variant="destructive" className="border-destructive/30 bg-destructive/5 text-destructive">
+            <AlertTriangle className="size-4" />
+            <AlertTitle>Không thể tạo thêm livestream</AlertTitle>
+            <AlertDescription>
+              Bạn đã sử dụng hết số lượng livestream active đồng thời tối đa của gói hiện tại ({active_streams_count}/{limitStreams} luồng).
+              Vui lòng kết thúc một livestream đang chạy hoặc nâng cấp gói dịch vụ để tiếp tục.
+            </AlertDescription>
+          </Alert>
+        )}
+
         {/* Submit */}
         <div className="flex items-center gap-3">
-          <Button type="submit" size="lg" disabled={form.processing}>
+          <Button type="submit" size="lg" disabled={form.processing || limitExceeded}>
             {form.processing ? (
```

### Proposed Controller Changes: `backend/app/Http/Controllers/SubscriptionController.php`
```php
    public function storePackage(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.limit_streams' => ['nullable', 'integer', 'min:-1'],
            'features.max_duration_hours' => ['nullable', 'integer', 'min:1'],
            'features.ai_credits' => ['nullable', 'integer', 'min:-1'],
            'features.audio_analysis' => ['nullable', 'boolean'],
            'features.export_leads' => ['nullable', 'boolean'],
        ]);

        SubscriptionPackage::create($validated);

        return back()->with('success', 'Đã tạo gói dịch vụ mới thành công.');
    }

    public function updatePackage(Request $request, SubscriptionPackage $package)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.limit_streams' => ['nullable', 'integer', 'min:-1'],
            'features.max_duration_hours' => ['nullable', 'integer', 'min:1'],
            'features.ai_credits' => ['nullable', 'integer', 'min:-1'],
            'features.audio_analysis' => ['nullable', 'boolean'],
            'features.export_leads' => ['nullable', 'boolean'],
        ]);

        $package->update($validated);

        return back()->with('success', 'Đã cập nhật gói dịch vụ thành công.');
    }

    public function destroyPackage(SubscriptionPackage $package)
    {
        $hasAssociations = UserSubscription::where('subscription_package_id', $package->id)->exists()
            || Transaction::where('subscription_package_id', $package->id)->exists();
        if ($hasAssociations) {
            return back()->withErrors(['error' => 'Không thể xóa gói dịch vụ đã có lịch sử đăng ký hoặc giao dịch.']);
        }
        try {
            $package->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Lỗi khi xóa gói dịch vụ: '.$e->getMessage()]);
        }

        return back()->with('success', 'Đã xóa gói dịch vụ thành công.');
    }
```

### Proposed Route Changes: `backend/routes/web.php`
```diff
@@ -218,43 +218,6 @@
-    Route::post('/packages', function (Request $request) {
-        $validated = $request->validate([
-            'name' => ['required', 'string', 'max:255'],
-            'price' => ['required', 'integer', 'min:0'],
-            'duration_days' => ['required', 'integer', 'min:1'],
-            'features' => ['nullable', 'array'],
-        ]);
-
-        SubscriptionPackage::create($validated);
-
-        return back()->with('success', 'Đã tạo gói dịch vụ mới thành công.');
-    })->name('admin.packages.store');
-
-    Route::put('/packages/{package}', function (Request $request, SubscriptionPackage $package) {
-        $validated = $request->validate([
-            'name' => ['required', 'string', 'max:255'],
-            'price' => ['required', 'integer', 'min:0'],
-            'duration_days' => ['required', 'integer', 'min:1'],
-            'features' => ['nullable', 'array'],
-        ]);
-
-        $package->update($validated);
-
-        return back()->with('success', 'Đã cập nhật gói dịch vụ thành công.');
-    })->name('admin.packages.update');
-
-    Route::delete('/packages/{package}', function (SubscriptionPackage $package) {
-        $hasAssociations = UserSubscription::where('subscription_package_id', $package->id)->exists()
-            || Transaction::where('subscription_package_id', $package->id)->exists();
-        if ($hasAssociations) {
-            return back()->withErrors(['error' => 'Không thể xóa gói dịch vụ đã có lịch sử đăng ký hoặc giao dịch.']);
-        }
-        try {
-            $package->delete();
-        } catch (Exception $e) {
-            return back()->withErrors(['error' => 'Lỗi khi xóa gói dịch vụ: '.$e->getMessage()]);
-        }
-
-        return back()->with('success', 'Đã xóa gói dịch vụ thành công.');
-    })->name('admin.packages.destroy');
+    Route::post('/packages', [SubscriptionController::class, 'storePackage'])->name('admin.packages.store');
+    Route::put('/packages/{package}', [SubscriptionController::class, 'updatePackage'])->name('admin.packages.update');
+    Route::delete('/packages/{package}', [SubscriptionController::class, 'destroyPackage'])->name('admin.packages.destroy');
```

---

## 5. Verification Method
1. **Automated Tests**:
   Ensure all existing test suites pass:
   ```bash
   php artisan test --filter=SubscriptionGatingTest
   ```
2. **Visual Inspection**:
   Inspect the modified files:
   - `backend/app/Http/Controllers/LiveSessionController.php`
   - `backend/resources/js/Pages/Lives/Setup.tsx`
   - `backend/app/Http/Controllers/SubscriptionController.php`
   - `backend/routes/web.php`
   - `backend/resources/js/Pages/Admin/Packages/Index.tsx`
