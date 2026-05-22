<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'duration_days',
        'features',
    ];

    protected $appends = ['features_list'];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_days' => 'integer',
            'features' => 'array',
        ];
    }

    /**
     * Get the dynamic features list as Vietnamese strings.
     */
    public function getFeaturesListAttribute(): array
    {
        $features = $this->features;
        if (!is_array($features)) {
            return [];
        }

        $list = [];

        // 1. Limit streams
        $limitStreams = $features['limit_streams'] ?? null;
        if ($limitStreams !== null) {
            if ($limitStreams === -1) {
                $list[] = 'Không giới hạn phiên livestream';
            } else {
                $list[] = "Tối đa {$limitStreams} phiên livestream";
            }
        }

        // 2. Max duration hours
        $maxDuration = $features['max_duration_hours'] ?? null;
        if ($maxDuration !== null) {
            if ($maxDuration === -1) {
                $list[] = 'Không giới hạn thời lượng phiên';
            } else {
                $list[] = "Thời lượng livestream tối đa {$maxDuration} giờ";
            }
        }

        // 3. AI credits
        $aiCredits = $features['ai_credits'] ?? null;
        if ($aiCredits !== null) {
            if ($aiCredits === -1) {
                $list[] = 'Không giới hạn bình luận phân tích AI';
            } else {
                $list[] = "Tối đa " . number_format($aiCredits) . " bình luận phân tích AI";
            }
        }

        // 4. Audio analysis
        $audioAnalysis = $features['audio_analysis'] ?? null;
        if (!empty($audioAnalysis)) {
            $list[] = 'Hỗ trợ phân tích âm thanh';
        }

        // 5. Export leads
        $exportLeads = $features['export_leads'] ?? null;
        if (!empty($exportLeads)) {
            $list[] = 'Hỗ trợ xuất Leads báo cáo';
        }

        return $list;
    }

    /**
     * Get all subscriptions for this package.
     */
    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'subscription_package_id');
    }
}
