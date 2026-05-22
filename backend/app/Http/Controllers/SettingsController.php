<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->resolveActiveSubscription();
        $activeSub = $user->activeSubscription;

        $activeStreamsCount = LiveSession::forUser($user->id)
            ->whereIn('status', ['connecting', 'live'])
            ->count();

        $cycleStart = $activeSub?->starts_at ?? now()->startOfMonth();
        $totalSessionsInCycle = LiveSession::forUser($user->id)
            ->where('created_at', '>=', $cycleStart)
            ->count();

        return Inertia::render('Settings/Index', [
            'settings' => $user->getSettingsWithDefaults(),
            'activeStreamsCount' => $activeStreamsCount,
            'totalSessionsInCycle' => $totalSessionsInCycle,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'ai_language' => ['required', 'in:vi,en,auto'],
            'auto_extract_phone' => ['required', 'boolean'],
            'auto_extract_address' => ['required', 'boolean'],
            'realtime_alerts' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $settings = array_merge($user->settings ?? [], $validated);
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Đã lưu cài đặt AI.');
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Đã cập nhật thông tin tài khoản.');
    }

    public function connectTikTok(Request $request)
    {
        $validated = $request->validate([
            'tiktok_username' => ['required', 'string', 'max:255'],
        ]);

        $username = $validated['tiktok_username'];
        if (! str_starts_with($username, '@')) {
            $username = '@'.$username;
        }

        $user = $request->user();
        $settings = $user->settings ?? [];
        $settings['tiktok_username'] = $username;
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Đã kết nối tài khoản TikTok thành công.');
    }

    public function disconnectTikTok(Request $request)
    {
        $user = $request->user();
        $settings = $user->settings ?? [];
        if (array_key_exists('tiktok_username', $settings)) {
            unset($settings['tiktok_username']);
        }
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Đã ngắt kết nối tài khoản TikTok.');
    }
}
