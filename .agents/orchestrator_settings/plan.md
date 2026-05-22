# Project: Settings Page Dynamization Plan
# Scope: settings_page_dynamics

## Architecture
- User settings are saved in the `users.settings` JSON column (including `tiktok_username`).
- Active subscription package details (price, duration_days, features) are shared via Inertia Shared Data (`HandleInertiaRequests`).
- Settings statistics (active stream counts, total sessions count) are calculated and passed dynamically from `SettingsController`.
- Routes are registered in `routes/web.php` under `auth` middleware.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|---|---|---|---|
| 1 | Backend Settings & Connections API | Implement TikTok connect/disconnect, update HandleInertiaRequests and SettingsController, register routes. | None | DONE |
| 2 | Frontend UI Settings & Stats | Update settings page with dynamic package info, feature list, active stats, and connect/disconnect forms/modals. | Milestone 1 | DONE |
| 3 | Verification & Testing | Create Feature tests for TikTok connections, run php artisan test, and verify assets build. | Milestone 2 | DONE |

## Interface Contracts
### Connect TikTok API
- Endpoint: `POST /settings/tiktok/connect`
- Payload: `{"tiktok_username": string}`
- Response: Redirect back with `success` flash message.

### Disconnect TikTok API
- Endpoint: `POST /settings/tiktok/disconnect`
- Payload: None
- Response: Redirect back with `success` flash message.

### Settings Statistics
- Prop: `statistics`
- Format:
  ```json
  {
    "active_streams_count": number,
    "total_sessions_count": number
  }
  ```

### Updated auth.subscription Props
- Price: `auth.subscription.price` (number)
- Duration: `auth.subscription.duration_days` (number)
- Settings TikTok: `auth.user.settings.tiktok_username` (string | null)
