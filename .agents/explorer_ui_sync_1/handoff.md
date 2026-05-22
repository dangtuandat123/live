# Handoff Report — Explorer UI Sync 1

## 1. Observation

I directly observed the following files and code implementations:

### A. Hardcoded Beneficiary Details in Frontend
In `backend/resources/js/Pages/Subscription/Index.tsx` (Lines 570–575), the bank details in the checkout modal are statically defined as "MB Bank" and "DANG TUAN DAT":
```tsx
<div className="flex justify-between items-center py-1 border-b border-border/40">
  <span className="text-muted-foreground">Ngân hàng:</span>
  <span className="font-semibold text-foreground">MB Bank</span>
</div>
<div className="flex justify-between items-center py-1 border-b border-border/40">
  <span className="text-muted-foreground">Chủ tài khoản:</span>
  <span className="font-semibold text-foreground">DANG TUAN DAT</span>
</div>
```

### B. Hardcoded Beneficiary Details in Checkout Controller
In `backend/app/Http/Controllers/SubscriptionController.php` (Lines 145–156), the VietQR URL uses a hardcoded template for the bank BIN (`970416`), account number (`11183041`), and account name (`DANG TUAN DAT`):
```php
$vietQrTemplate = 'https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}';

$prefixVal = rawurlencode($paymentConfig->prefix ?? '');
$userIdVal = rawurlencode((string) $user->id);
$suffixVal = rawurlencode($paymentConfig->suffix ?? '');
$amountVal = rawurlencode((string) $package->price);

$vietQrUrl = str_replace(
    ['{Prefix}', '{userId}', '{Suffix}', '{amount}'],
    [$prefixVal, $userIdVal, $suffixVal, $amountVal],
    $vietQrTemplate
);
```
The returned JSON response only provides the transaction ID and the URL (Lines 168–171):
```php
return response()->json([
    'transaction_id' => $transaction->transaction_id,
    'vietqr_url' => $transaction->vietqr_url,
]);
```

### C. Active PaymentConfig Retrieval logic
The active `PaymentConfig` is queried using `PaymentConfig::where('is_active', true)->first()` in `SubscriptionController.php` (Line 137). However, the `payment_configs` table schema does not currently define fields for `bank_name`, `bank_id` (BIN), `account_no`, `account_name`, or `qr_template`.

### D. Revenue Card Absence in Admin Payments Page
In `backend/resources/js/Pages/Admin/Payments/Index.tsx`, there is currently no KPI/statistics card for showing revenue. The `/payments` GET route in `backend/routes/web.php` (Lines 261–267) only fetches the payment config and does not query or pass any transaction data:
```php
Route::get('/payments', function () {
    $config = PaymentConfig::where('is_active', true)->first()
        ?? PaymentConfig::first()
        ?? new PaymentConfig;

    return Inertia::render('Admin/Payments/Index', ['config' => $config]);
})->name('admin.payments.index');
```

---

## 2. Logic Chain

1. Since `payment_configs` is the dynamic repository of payment configurations in the database (Observation C), adding the beneficiary details (`bank_name`, `bank_id`, `account_no`, `account_name`, `qr_template`) to this table is the most direct way to eliminate hardcoding.
2. In `SubscriptionController.php`, if the controller retrieves the active `PaymentConfig`, we can replace the hardcoded VietQR template string with the config's template and placeholder replacements (Observation B).
3. If the controller returns these beneficiary attributes in the checkout API response, the frontend React checkout modal can render them dynamically instead of showing the hardcoded strings (Observation A).
4. By updating the admin settings page `Admin/Payments/Index.tsx` to handle these bank details, the administrator can configure and update them dynamically.
5. In `routes/web.php`, by performing a query to sum successful transactions amount (`Transaction::where('status', 'success')->sum('amount')`) and passing the resulting value as an Inertia prop to `Admin/Payments/Index.tsx`, we can replace the simulated revenue value with the actual database total (Observation D).

---

## 3. Caveats

- We assume the template format `{bank_id}-{account_no}` for VietQR remains compatible with external bank configurations. If a different format is required, the template string in the database can be edited.
- Outbound webhooks and external banking integrations depend on external connectivity, which was not tested due to network constraints.

---

## 4. Conclusion

We must implement a dynamic payment configuration system and aggregate actual transactions revenue in the Admin panel. Below is the proposed implementation plan:

### Step 1: Database Schema Migration
Create a new migration `database/migrations/2026_05_22_000000_add_beneficiary_details_to_payment_configs_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->string('bank_id')->nullable();
            $table->string('account_no')->nullable();
            $table->string('account_name')->nullable();
            $table->text('qr_template')->nullable();
        });

        // Seed default beneficiary details for existing configs
        \DB::table('payment_configs')->update([
            'bank_name' => 'MB Bank',
            'bank_id' => '970416',
            'account_no' => '11183041',
            'account_name' => 'DANG TUAN DAT',
            'qr_template' => 'https://api.vietqr.io/image/{bank_id}-{account_no}-rdXzPHV.jpg?accountName={account_name}&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}',
        ]);
    }

    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_id', 'account_no', 'account_name', 'qr_template']);
        });
    }
};
```

### Step 2: Update PaymentConfig Model & Seeder
- **`app/Models/PaymentConfig.php`**: Add the new columns to `$fillable`:
  ```php
  'bank_name', 'bank_id', 'account_no', 'account_name', 'qr_template'
  ```
- **`database/seeders/PaymentConfigSeeder.php`**: Add defaults to the VietQR configuration:
  ```php
  'bank_name' => 'MB Bank',
  'bank_id' => '970416',
  'account_no' => '11183041',
  'account_name' => 'DANG TUAN DAT',
  'qr_template' => 'https://api.vietqr.io/image/{bank_id}-{account_no}-rdXzPHV.jpg?accountName={account_name}&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}',
  ```

### Step 3: Refactor SubscriptionController Checkout API
In `app/Http/Controllers/SubscriptionController.php`, update the paid package checkout flow:
```php
        $vietQrTemplate = $paymentConfig->qr_template ?? 'https://api.vietqr.io/image/970416-11183041-rdXzPHV.jpg?accountName=DANG%20TUAN%20DAT&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}';

        $bankIdVal = rawurlencode($paymentConfig->bank_id ?? '970416');
        $accountNoVal = rawurlencode($paymentConfig->account_no ?? '11183041');
        $accountNameVal = rawurlencode($paymentConfig->account_name ?? 'DANG TUAN DAT');
        $prefixVal = rawurlencode($paymentConfig->prefix ?? '');
        $userIdVal = rawurlencode((string) $user->id);
        $suffixVal = rawurlencode($paymentConfig->suffix ?? '');
        $amountVal = rawurlencode((string) $package->price);

        $vietQrUrl = str_replace(
            ['{bank_id}', '{account_no}', '{account_name}', '{Prefix}', '{userId}', '{Suffix}', '{amount}'],
            [$bankIdVal, $accountNoVal, $accountNameVal, $prefixVal, $userIdVal, $suffixVal, $amountVal],
            $vietQrTemplate
        );
        
        // ... (transaction creation) ...

        return response()->json([
            'transaction_id' => $transaction->transaction_id,
            'vietqr_url' => $transaction->vietqr_url,
            'beneficiary_bank' => $paymentConfig->bank_name ?? 'MB Bank',
            'beneficiary_account' => $paymentConfig->account_no ?? '11183041',
            'beneficiary_name' => $paymentConfig->account_name ?? 'DANG TUAN DAT',
        ]);
```

### Step 4: Update Admin Settings Routes & Validation
In `routes/web.php`, update the PUT `/admin/payments` route:
- Add validation:
  ```php
  'bank_name' => ['nullable', 'string', 'max:255'],
  'bank_id' => ['nullable', 'string', 'max:50'],
  'account_no' => ['nullable', 'string', 'max:100'],
  'account_name' => ['nullable', 'string', 'max:255'],
  'qr_template' => ['nullable', 'string', 'max:1000'],
  ```
- Fetch and pass `total_revenue` in the GET `/payments` route:
  ```php
  Route::get('/payments', function () {
      $config = PaymentConfig::where('is_active', true)->first()
          ?? PaymentConfig::first()
          ?? new PaymentConfig;

      $totalRevenue = \App\Models\Transaction::where('status', 'success')->sum('amount');

      return Inertia::render('Admin/Payments/Index', [
          'config' => $config,
          'total_revenue' => $totalRevenue,
      ]);
  })->name('admin.payments.index');
  ```

### Step 5: Update Admin Payments Interface (`Admin/Payments/Index.tsx`)
- Accept `total_revenue` as a prop:
  ```tsx
  interface Props {
    config: PaymentConfigData
    total_revenue: number
  }
  ```
- Initialize the form with new fields:
  ```tsx
  bank_name: config.bank_name || "",
  bank_id: config.bank_id || "",
  account_no: config.account_no || "",
  account_name: config.account_name || "",
  qr_template: config.qr_template || "",
  ```
- Render a **Total Revenue card** at the top of the form layout:
  ```tsx
  <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mb-6">
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">Tổng doanh thu</CardTitle>
        <DollarSignIcon className="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">
          {new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
          }).format(total_revenue)}
        </div>
        <p className="text-xs text-muted-foreground">+20.1% so với tháng trước</p>
      </CardContent>
    </Card>
  </div>
  ```
- Add text inputs to configure:
  1. `bank_name` (Ngân hàng thụ hưởng)
  2. `bank_id` (Mã BIN Ngân hàng)
  3. `account_no` (Số tài khoản thụ hưởng)
  4. `account_name` (Tên chủ tài khoản)
  5. `qr_template` (Template URL VietQR)

### Step 6: Update Subscription Page Checkout Modal (`Subscription/Index.tsx`)
- Update `checkoutData` state typing to support beneficiary properties:
  ```tsx
  const [checkoutData, setCheckoutData] = React.useState<{
    transaction_id: string
    vietqr_url: string | null
    beneficiary_bank?: string
    beneficiary_account?: string
    beneficiary_name?: string
  } | null>(null)
  ```
- Replace hardcoded text in checkout modal with dynamic state:
  ```tsx
  <span className="font-semibold text-foreground">{checkoutData?.beneficiary_bank || "MB Bank"}</span>
  // ...
  <span className="font-semibold text-foreground">{checkoutData?.beneficiary_name || "DANG TUAN DAT"}</span>
  ```

---

## 5. Verification Method

To verify these changes after implementation:
1. **Migration and Seeders**:
   Run:
   ```powershell
   php artisan migrate
   php artisan db:seed --class=PaymentConfigSeeder
   ```
   Verify the table columns exist and defaults are loaded in `payment_configs`.
2. **Automated Feature Tests**:
   Run:
   ```powershell
   php artisan test --filter SubscriptionPaymentTest
   ```
   All tests should pass. Add test assertions verifying that checkout response contains `beneficiary_bank`, `beneficiary_account`, and `beneficiary_name`.
3. **Compilation**:
   Run:
   ```powershell
   npm run build
   ```
   Ensure compilation finishes with no TypeScript errors.
