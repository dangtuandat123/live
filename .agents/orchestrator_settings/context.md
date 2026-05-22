# Context & Research Notes

## User Request Context
The user wants to make the Settings page (`/settings`) fully dynamic by:
- Loading subscription package details dynamically (price, duration, limit_streams, max_duration_hours, ai_credits, features).
- Dynamically displaying the TikTok 플랫폼 connection state based on user's `settings` JSON column (checking `tiktok_username`).
- Providing an API to connect a new TikTok account (saving username) and disconnect.
- Dynamically showing resource usage statistics: active streams vs limit streams, and total livestream sessions created in the current subscription cycle or this month.

## Initial Files list to inspect
- `routes/web.php` or `routes/api.php` for Settings Controller routes.
- SettingsController: `backend/app/Http/Controllers/SettingsController.php` or similar.
- Frontend Settings Page: `resources/js/Pages/Settings.tsx` or similar.
- HandleInertiaRequests middleware: `backend/app/Http/Middleware/HandleInertiaRequests.php`.
- User Model: `backend/app/Models/User.php`.
