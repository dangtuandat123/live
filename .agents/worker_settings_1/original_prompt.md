## 2026-05-22T05:45:20Z
Perform the following changes to make the Settings page dynamic and format the UI layout according to requirements:

1. **User Model Defaults**:
   In `backend/app/Models/User.php`, add `'tiktok_username' => null` to the `DEFAULT_SETTINGS` constant.

2. **Inertia Share Middleware**:
   In `backend/app/Http/Middleware/HandleInertiaRequests.php`, update the shared `'subscription'` array:
   - Include `'price'` and `'duration_days'` fetched from the active subscription's package (e.g. `$activeSub?->package?->price ?? 0`, `$activeSub?->package?->duration_days ?? 30` or similar defaults).
   - Include `'active_streams_count'` (count of users' `LiveSession` records with status `connecting` or `live`).
   - Include `'total_sessions_in_cycle'` (count of users' `LiveSession` records created at or after the subscription `$activeSub->starts_at`, or the start of the current month if no active subscription exists).

3. **Settings Route Registration**:
   In `backend/routes/web.php`, add endpoints for TikTok connection:
   - `POST /settings/tiktok/connect` map to `SettingsController@connectTikTok`
   - `POST /settings/tiktok/disconnect` map to `SettingsController@disconnectTikTok`
   Ensure they are registered under the existing auth group middleware.

4. **Settings Controller Actions**:
   In `backend/app/Http/Controllers/SettingsController.php`:
   - In `index(Request $request)`, query:
     - `$activeStreamsCount` (count of users' `LiveSession` records with status `connecting` or `live`).
     - `$totalSessionsInCycle` (count of users' `LiveSession` records created at or after the subscription `$activeSub->starts_at`, or the start of the current month if no active subscription exists).
     Pass both as props to the frontend View (`activeStreamsCount` and `totalSessionsInCycle`).
   - In `updateSettings(Request $request)`:
     Use `array_merge($user->settings ?? [], $validated)` instead of replacing the entire array, protecting the `tiktok_username` key.
   - Implement `connectTikTok(Request $request)`:
     - Validate input: `'tiktok_username' => ['required', 'string', 'max:255']`.
     - Normalize username to prepend `@` if it doesn't already start with `@` (e.g. `username` -> `@username`).
     - Save to `$user->settings` array under key `'tiktok_username'`.
     - Redirect back with success message.
   - Implement `disconnectTikTok(Request $request)`:
     - Remove key `'tiktok_username'` from `$user->settings` array (using `unset` or setting it to `null`, ensuring it gets saved/removed properly).
     - Redirect back with success message.

5. **TypeScript Typings**:
   In `backend/resources/js/types/index.d.ts`, update interface declarations for `UserSettings`, `User`, and `UserSubscription`:
   - Ensure `UserSettings` includes `tiktok_username?: string | null`.
   - Ensure `UserSubscription` includes optional/required properties: `price?: number`, `duration_days?: number`, `active_streams_count?: number`, `total_sessions_in_cycle?: number`.

6. **Frontend Settings Page**:
   In `backend/resources/js/Pages/Settings/Index.tsx`:
   - Update component signature to accept new props: `activeStreamsCount` and `totalSessionsInCycle`.
   - Read `auth.subscription` dynamically. Render dynamic pricing, duration, and list of features.
   - For Platforms: dynamically render TikTok connection state based on `settings.tiktok_username` (e.g. display the connected handle, or "Chưa kết nối").
   - Support "Kết nối" button that opens a simple shadcn/ui Dialog modal where the user inputs the username, submits a POST request to `settings.tiktok.connect`, handles validation errors and loading states.
   - Support "Ngắt kết nối" button that displays a confirm dialogue (`confirm('...')`) and submits a POST request to `settings.tiktok.disconnect` on confirm.

7. **Client-side Gating and Padding in Setup page**:
   In `backend/resources/js/Pages/Lives/Setup.tsx`:
   - Change main wrapper padding class from `p-4 pt-4` to `p-6` to conform to general layout standards.
   - Update the submit gating check to prevent creating new livestream when current active stream count (`auth.subscription.active_streams_count` or the prop `active_streams_count`) reaches or exceeds the package limit (`limit_streams`). Display a clean warning message.

8. **Padding in Profile Page**:
   In `backend/resources/js/Pages/Profile/Edit.tsx`, change main wrapper padding class from `p-4 pt-4` to `p-6`.

9. **Checkout Modal Adjustments**:
   In `backend/resources/js/Pages/Subscription/Index.tsx`:
   - Adjust `DialogContent` max height from `max-h-[85vh]` to `max-h-[92vh]`.
   - Reduce dialog wrapper padding from `p-5` to `p-3 px-4`, reduce inner gaps and `space-y-4` to `space-y-3`/`gap-4` to save vertical space.
   - Scale down the QR code container from `max-w-[155px]` to `max-w-[130px]`.
   - Ensure all elements including footer buttons are fully visible without truncation on 768px height viewports.

10. **Livestream Status Badges**:
    In `backend/resources/js/Pages/Lives/Index.tsx` and `backend/resources/js/Pages/Lives/Show.tsx`, redesign the badges (statuses: `connecting`, `disconnected`, `ended`, `live` or others) to use the premium translucent style (`bg-.../10 text-... border border-.../20` with pulse/ping animations).

11. **Landing Page Buttons**:
    In `backend/resources/views/landing.blade.php`, update the `<a>` buttons at lines 770 and 814 to include the `w-full` class.

12. **Automated Feature Tests**:
    Create a feature test file `backend/tests/Feature/TikTokConnectionTest.php` testing:
    - User can load Settings view successfully.
    - User can connect a TikTok username (POST request, check that username starts with `@`).
    - User can disconnect a TikTok username (POST request, check username removed).
    - User updating AI settings doesn't overwrite the TikTok username connection.

13. **Verification**:
    - Run the new and all other Laravel tests: `php artisan test`.
    - Run the assets build: `npm run build` inside the `backend` folder to ensure clean TypeScript compilation.

Please load and apply the Laravel Best Practices skill during development: d:\Workspace\livestream\.agents\skills\laravel-best-practices\SKILL.md.
