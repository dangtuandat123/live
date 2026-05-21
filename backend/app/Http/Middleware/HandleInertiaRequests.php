<?php

namespace App\Http\Middleware;

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

        if ($user) {
            $user->resolveActiveSubscription();
            $activeSub = $user->activeSubscription;

            $subscription = [
                'active' => (bool) $activeSub?->isActive(),
                'package_id' => $activeSub?->subscription_package_id,
                'package_name' => $activeSub?->package?->name ?? 'Free',
                'expires_at' => $activeSub?->expires_at?->toISOString(),
                'used_ai_credits' => $activeSub?->used_ai_credits ?? 0,
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
