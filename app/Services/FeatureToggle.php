<?php

namespace App\Services;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

class FeatureToggle
{
    public function enabled(string $key): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        return Cache::remember(
            "feature_flag_{$key}",
            now()->addMinutes(5),
            fn () => FeatureFlag::query()->forKey($key)->where('enabled', true)->exists()
        );
    }

    public function flush(string $key): void
    {
        Cache::forget("feature_flag_{$key}");
    }
}
