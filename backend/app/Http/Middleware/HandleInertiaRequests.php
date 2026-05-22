<?php

namespace App\Http\Middleware;

use App\Models\LiveSession;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $subscription = null;

        // Skip resolving active subscription for non-Inertia JSON requests (e.g. API endpoints under web middleware)
        // to avoid auto-subscribing users during checkout API calls.
        $isInertiaRequest = ! $request->expectsJson() || $request->hasHeader('X-Inertia');

        if ($user && $isInertiaRequest) {
            $user->resolveActiveSubscription();
            $activeSub = $user->activeSubscription;

            $activeStreamsCount = LiveSession::forUser($user->id)
                ->whereIn('status', ['connecting', 'live'])
                ->count();

            $cycleStart = $activeSub?->starts_at ?? now()->startOfMonth();
            $totalSessionsInCycle = LiveSession::forUser($user->id)
                ->where('created_at', '>=', $cycleStart)
                ->count();

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
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'subscription' => $subscription,
            ],
        ];
    }
}
