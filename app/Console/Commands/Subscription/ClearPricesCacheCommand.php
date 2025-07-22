<?php

namespace App\Console\Commands\Subscription;

use Illuminate\Support\Facades\Schema;
use App\Console\Commands\BaseCommand;
use Illuminate\Support\Facades\Cache;

class ClearPricesCacheCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:clear-prices-cache 
                            {--all : Clear all subscription-related cache}
                            {--pattern= : Clear cache matching specific pattern}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear cached Stripe prices data and related subscription cache';

    /**
     * Cache patterns to clear
     */
    private const CACHE_PATTERNS = [
        'stripe_prices'      => 'stripe_prices_*',
        'subscription_plans' => 'subscription_plans_*',
        'product_features'   => 'product_features_*',
        'pricing_tiers'      => 'pricing_tiers_*',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Clearing subscription cache...');

        if ($this->option('all')) {
            return $this->clearAllCache();
        }

        if ($pattern = $this->option('pattern')) {
            return $this->clearCacheByPattern($pattern);
        }

        return $this->clearPricesCache();
    }

    /**
     * Clear all subscription-related cache
     */
    private function clearAllCache(): int
    {
        $clearedCount = 0;

        foreach (self::CACHE_PATTERNS as $name => $pattern) {
            $count = $this->clearCachePattern($pattern);
            $this->line("  ✓ Cleared {$count} {$name} cache entries");
            $clearedCount += $count;
        }

        $this->info("Successfully cleared {$clearedCount} total cache entries.");
        return self::SUCCESS;
    }

    /**
     * Clear cache by specific pattern
     */
    private function clearCacheByPattern(string $pattern): int
    {
        $count = $this->clearCachePattern($pattern);
        $this->info("Cleared {$count} cache entries matching pattern: {$pattern}");
        return self::SUCCESS;
    }

    /**
     * Clear only Stripe prices cache
     */
    private function clearPricesCache(): int
    {
        $specificKeys = [
            'stripe_prices',
            'stripe_prices_active',
            'stripe_prices_by_product',
            'subscription_pricing_config'
        ];

        $clearedCount = 0;

        // Avoid cache usage if cache table does not exist (e.g. during install)
        try {
            if (Schema::hasTable('cache')) {
                foreach ($specificKeys as $key) {
                    if (Cache::forget($key)) {
                        $clearedCount++;
                        $this->line("  ✓ Cleared cache key: {$key}");
                    }
                }
                // Clear pattern-based cache
                $patternCount = $this->clearCachePattern('stripe_prices_*');
                $clearedCount += $patternCount;
                if ($patternCount > 0) {
                    $this->line("  ✓ Cleared {$patternCount} pattern-based cache entries");
                }
            } else {
                $this->warn('Cache table does not exist. Skipping cache clear.');
            }
        } catch (\Exception $e) {
            $this->warn('Cache clear failed: ' . $e->getMessage());
        }

        $this->info("Successfully cleared {$clearedCount} Stripe prices cache entries.");
        return self::SUCCESS;
    }

    /**
     * Clear cache entries matching a pattern
     */
    private function clearCachePattern(string $pattern): int
    {
        $store = Cache::getStore();

        // For Redis/Memcached stores that support pattern deletion
        if (method_exists($store, 'flush') && str_contains($pattern, '*')) {
            try {
                // This is a simplified approach - in production you might want
                // to use more sophisticated pattern matching
                $keys  = $this->getCacheKeys($pattern);
                $count = 0;

                foreach ($keys as $key) {
                    if (Cache::forget($key)) {
                        $count++;
                    }
                }

                return $count;
            } catch (\Exception $e) {
                $this->warn("Could not clear pattern cache: {$e->getMessage()}");
                return 0;
            }
        }

        // Fallback: just try to forget the pattern as a literal key
        return Cache::forget($pattern) ? 1 : 0;
    }

    /**
     * Get cache keys matching pattern (simplified version)
     */
    private function getCacheKeys(string $pattern): array
    {
        // This is a simplified implementation
        // In a real application, you'd implement proper pattern matching
        // based on your cache driver (Redis, Memcached, etc.)

        $basePattern  = str_replace('*', '', $pattern);
        $possibleKeys = [
            $basePattern,
            $basePattern . 'usd',
            $basePattern . 'eur',
            $basePattern . 'monthly',
            $basePattern . 'yearly',
            $basePattern . 'active',
            $basePattern . 'inactive',
        ];

        return array_filter($possibleKeys, fn ($key) => Cache::has($key));
    }
}
