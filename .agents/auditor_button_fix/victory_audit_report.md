# Forensic Audit Report

**Work Product**: d:\Workspace\livestream\backend
**Profile**: General Project
**Verdict**: CLEAN (PASS)

### Phase Results
- **Check 1: "Bắt đầu ngay" button layout** — PASS: Verification of `backend/resources/views/landing.blade.php` line 770 shows that the anchor tag contains the class `w-full`.
- **Check 2: "Đăng ký ngay" button layout** — PASS: Verification of `backend/resources/views/landing.blade.php` line 814 shows that the anchor tag contains the class `w-full`.
- **Check 3: Backend test suite run** — PASS: Execution of `php artisan test` succeeded with 78 passed tests.
- **Check 4: Frontend asset compilation** — PASS: Execution of `npm run build` compiled client resources successfully with zero compilation errors.
- **Check 5: Hardcoded output detection** — PASS: No fabricated results or fake test runners found in the codebase.
- **Check 6: Facade detection** — PASS: Subscriptions, payment gateways, and queue jobs have real backend database logic and integration tests.
- **Check 7: Pre-populated artifact detection** — PASS: No stale/pre-existing log files or fake output files are checked into the repository.
- **Check 8: Dependency audit** — PASS: Standard Laravel framework features are utilized properly with no illegal delegation.

---

### Evidence

#### 1. Landing Page View Anchor Tags (`backend/resources/views/landing.blade.php`)

**Lines 770-772 ("Bắt đầu ngay"):**
```html
                    <a href="{{ route('register') }}" class="w-full mt-8 inline-flex h-10 items-center justify-center rounded-xl border border-border bg-background text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
                        Bắt đầu ngay
                    </a>
```

**Lines 814-816 ("Đăng ký ngay"):**
```html
                    <a href="{{ route('register') }}" class="w-full mt-8 inline-flex h-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-md shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.01]">
                        Đăng ký ngay
                    </a>
```

---

#### 2. Backend Test Output (`php artisan test`)
```
   PASS  Tests\Feature\Auth\AuthenticationTest
  ✓ login screen can be rendered                                                                                 0.02s  
  ✓ users can authenticate using the login screen                                                                0.03s  
  ✓ users can not authenticate with invalid password                                                             0.22s  
  ✓ users can logout                                                                                             0.02s  

   PASS  Tests\Feature\Auth\EmailVerificationTest
  ✓ email verification screen can be rendered                                                                    0.02s  
  ✓ email can be verified                                                                                        0.02s  
  ✓ email is not verified with invalid hash                                                                      0.02s  

   PASS  Tests\Feature\Auth\PasswordConfirmationTest
  ✓ confirm password screen can be rendered                                                                      0.02s  
  ✓ password can be confirmed                                                                                    0.02s  
  ✓ password is not confirmed with invalid password                                                              0.22s  

   PASS  Tests\Feature\Auth\PasswordResetTest
  ✓ reset password link screen can be rendered                                                                   0.02s  
  ✓ reset password link can be requested                                                                         0.22s  
  ✓ reset password screen can be rendered                                                                        0.23s  
  ✓ password can be reset with valid token                                                                       0.23s  

   PASS  Tests\Feature\Auth\PasswordUpdateTest
  ✓ password can be updated                                                                                      0.03s  
  ✓ correct password must be provided to update password                                                         0.02s  

   PASS  Tests\Feature\Auth\RegistrationTest
  ✓ registration screen can be rendered                                                                          0.02s  
  ✓ new users can register                                                                                       0.02s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                0.02s  

   PASS  Tests\Feature\ProfileTest
  ✓ profile page is displayed                                                                                    0.02s  
  ✓ profile information can be updated                                                                           0.02s  
  ✓ email verification status is unchanged when the email address is unchanged                                   0.02s  
  ✓ user can delete their account                                                                                0.02s  
  ✓ correct password must be provided to delete account                                                          0.02s  

   PASS  Tests\Feature\SubscriptionDatabaseTest
  ✓ subscription package creation and casts                                                                      0.02s  
  ✓ user subscription relations and active status                                                                0.02s  
  ✓ payment config creation and casts                                                                            0.01s  
  ✓ transaction creation and relations                                                                           0.02s  
  ✓ model default attributes                                                                                     0.01s  
  ✓ future starts at subscription is not active                                                                  0.02s  
  ✓ cascade delete restrictions                                                                                  0.02s  
  ✓ payment config cascade delete restrictions                                                                   0.02s  
  ✓ user subscription used ai credits                                                                            0.02s  

   PASS  Tests\Feature\SubscriptionGatingTest
  ✓ stream limit gating                                                                                          0.02s  
  ✓ stream duration limit gating                                                                                 0.82s  
  ✓ ai credits limit gating                                                                                      0.02s  
  ✓ audio analysis gating                                                                                        0.02s  
  ✓ inertia props sharing                                                                                        0.02s  
  ✓ subscription route props                                                                                     0.02s  
  ✓ stream unlimited duration gating                                                                             0.33s  

   PASS  Tests\Feature\SubscriptionPaymentChallengerTest
  ✓ callback same price different package bug                                                                    0.04s  
  ✓ callback duplicate requests cause double crediting                                                           0.04s  
  ✓ free package checkout infinite abuse                                                                         0.02s  
  ✓ package delete association prevention                                                                        0.03s  
  ✓ package crud validation min minus one                                                                        0.03s  

   PASS  Tests\Feature\SubscriptionPaymentTest
  ✓ packages listing returns seeded packages                                                                     0.02s  
  ✓ user subscription status endpoint returns correct response                                                   0.02s  
  ✓ checkout free package activates instantly without vietqr                                                     0.02s  
  ✓ checkout paid package generates vietqr url and creates pending transaction                                   0.02s  
  ✓ checkout returns 503 if no active payment config                                                             0.02s  
  ✓ callback processes payment upgrades subscription and marks transaction success                               0.02s  
  ✓ callback extends active subscription of same package                                                         0.02s  
  ✓ callback deactivates old subscription if different package                                                   0.02s  
  ✓ callback returns 422 if no package matches price                                                             0.02s  
  ✓ callback returns 500 if no active payment config                                                             0.02s  
  ✓ outbound webhook job sends http request with correct replacements                                            0.03s  

  Tests:    78 passed (573 assertions)
  Duration: 4.41s
```

---

#### 3. Frontend Build Output (`npm run build`)
```
> build
> tsc && vite build

vite v7.3.3 building client environment for production...
transforming...
✓ 3412 modules transformed.
rendering chunks...
computing gzip size...
public/build/manifest.json                                     23.80 kB │ gzip:   2.36 kB
public/build/assets/app-BQrsAOjD.css                          171.72 kB │ gzip:  25.91 kB
...
public/build/assets/app-1FOtWlhu.js                           518.50 kB │ gzip: 166.95 kB
✓ built in 6.67s
```
