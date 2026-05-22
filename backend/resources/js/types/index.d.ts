export interface UserSettings {
    ai_language?: string;
    auto_extract_phone?: boolean;
    auto_extract_address?: boolean;
    realtime_alerts?: boolean;
    tiktok_username?: string | null;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    created_at?: string;
    role: 'user' | 'admin';
    plan_name?: string;
    settings?: UserSettings;
}

export interface UserSubscriptionFeatures {
    limit_streams?: number;
    max_duration_hours?: number;
    ai_credits?: number;
    audio_analysis?: boolean;
    export_leads?: boolean;
}

export interface UserSubscription {
    active: boolean;
    package_id: number | null;
    package_name: string;
    price?: number;
    duration_days?: number;
    expires_at: string | null;
    used_ai_credits: number;
    active_streams_count?: number;
    total_sessions_in_cycle?: number;
    features: UserSubscriptionFeatures;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        subscription: UserSubscription | null;
    };
};
