<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Settings/Index', [
            'settings' => $request->user()->getSettingsWithDefaults(),
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

        $request->user()->update(['settings' => $validated]);

        return back()->with('success', 'Đã lưu cài đặt AI.');
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Đã cập nhật thông tin tài khoản.');
    }
}
