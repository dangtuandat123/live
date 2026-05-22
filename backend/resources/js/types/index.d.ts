export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    created_at?: string;
    role: 'user' | 'admin';
    plan_name?: string;
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
    expires_at: string | null;
    used_ai_credits: number;
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
