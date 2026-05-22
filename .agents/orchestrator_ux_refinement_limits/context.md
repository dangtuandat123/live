# Context Tracker

## Project Overview
Refining the UX/UI for subscription limits across the livestream comment analysis application. The changes are purely client-side improvements and UI enhancements to handle package limitations elegantly.

## Key Files Identified
- **Lives Show Page**: `backend/resources/js/Pages/Lives/Show.tsx`
- **Lives Setup Page**: `backend/resources/js/Pages/Lives/Setup.tsx`
- **Lives Index Page**: `backend/resources/js/Pages/Lives/Index.tsx`
- **Dashboard Page**: `backend/resources/js/Pages/Dashboard.tsx`
- **Sidebar Component**: `backend/resources/js/Components/app-sidebar.tsx`
- **Gating Test**: `backend/tests/Feature/SubscriptionGatingTest.php`

## Subscription Rules & Limits
- Limit parameters likely stored in user subscriptions or packages (e.g. `max_duration`, `max_streams`, `ai_credits`, `audio_analysis`, `csv_export`).
- When stream limit or credit limit is reached, UI warning/upgrade flows should trigger cleanly.
