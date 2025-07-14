<?php

namespace App\Console\Commands\Subscription;

use App\Console\Commands\BaseCommand;
use App\Modules\Subscription\Services\SubscriptionService;

class ShowPricesCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:show-prices 
                            {--currency= : Show prices for specific currency}
                            {--product= : Show prices for specific product}
                            {--format=table : Output format (table, json, csv)}
                            {--active-only : Show only active prices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show current subscription prices from Stripe';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): int
    {
        try {
            $this->info('Fetching subscription prices from Stripe...');
            
            $prices = $this->getPrices($subscriptionService);
            
            if (empty($prices)) {
                $this->warn('No prices found matching the criteria.');
                return self::SUCCESS;
            }
            
            $this->displayPrices($prices);
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error fetching prices: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Get prices based on options
     */
    private function getPrices(SubscriptionService $subscriptionService): array
    {
        $currency = $this->option('currency');
        $activeOnly = $this->option('active-only');
        
        // Get configured plans and their prices
        $plans = config('subscription.plans', []);
        $currencies = $currency ? [$currency] : array_keys(config('subscription.currencies', []));
        
        $allPrices = [];
        
        foreach ($currencies as $curr) {
            foreach ($plans as $planKey => $plan) {
                if (isset($plan['currencies'][$curr])) {
                    $planCurrency = $plan['currencies'][$curr];
                    
                    try {
                        // Try to get price from Stripe API
                        $stripePrice = $this->getStripePriceFromApi($planCurrency['stripe_id']);
                        
                        if ($stripePrice && (!$activeOnly || $stripePrice['active'])) {
                            $allPrices[] = array_merge($stripePrice, [
                                'plan_key' => $planKey,
                                'plan_name' => $plan['name'],
                                'interval' => $plan['interval'] ?? 'month',
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Fallback to configuration data
                        if (!$activeOnly) {
                            $allPrices[] = [
                                'id' => $planCurrency['stripe_id'],
                                'product_name' => $plan['name'],
                                'unit_amount' => ($planCurrency['fallback_price'] ?? 0) * 100,
                                'currency' => $curr,
                                'active' => false,
                                'created' => null,
                                'recurring' => [
                                    'interval' => $plan['interval'] ?? 'month',
                                    'interval_count' => 1
                                ],
                                'plan_key' => $planKey,
                                'plan_name' => $plan['name'],
                                'interval' => $plan['interval'] ?? 'month',
                                'source' => 'fallback'
                            ];
                        }
                    }
                }
            }
        }
        
        return $allPrices;
    }

    /**
     * Get price information from Stripe API
     */
    private function getStripePriceFromApi(string $priceId): ?array
    {
        try {
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            $price = $stripe->prices->retrieve($priceId, ['expand' => ['product']]);
            
            return [
                'id' => $price->id,
                'product_name' => $price->product->name ?? 'Unknown Product',
                'unit_amount' => $price->unit_amount,
                'currency' => $price->currency,
                'active' => $price->active,
                'created' => $price->created,
                'recurring' => $price->recurring ? [
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count
                ] : null,
                'source' => 'stripe'
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Display prices in requested format
     */
    private function displayPrices(array $prices): void
    {
        $format = $this->option('format');
        
        match ($format) {
            'json' => $this->displayAsJson($prices),
            'csv' => $this->displayAsCsv($prices),
            default => $this->displayAsTable($prices)
        };
    }

    /**
     * Display prices as table
     */
    private function displayAsTable(array $prices): void
    {
        $headers = [
            'ID', 'Product', 'Amount', 'Currency', 
            'Interval', 'Active', 'Created'
        ];
        
        $rows = array_map(function ($price) {
            return [
                $price['id'] ?? 'N/A',
                $price['product_name'] ?? 'Unknown',
                $this->formatAmount($price['unit_amount'] ?? 0, $price['currency'] ?? 'usd'),
                strtoupper($price['currency'] ?? 'USD'),
                $this->formatInterval($price),
                $price['active'] ? '✓' : '✗',
                isset($price['created']) ? date('Y-m-d', $price['created']) : 'N/A',
            ];
        }, $prices);
        
        $this->table($headers, $rows);
        $this->info("Total: " . count($prices) . " price(s)");
    }

    /**
     * Display prices as JSON
     */
    private function displayAsJson(array $prices): void
    {
        $this->line(json_encode($prices, JSON_PRETTY_PRINT));
    }

    /**
     * Display prices as CSV
     */
    private function displayAsCsv(array $prices): void
    {
        $headers = ['ID', 'Product', 'Amount', 'Currency', 'Interval', 'Active', 'Created'];
        $this->line(implode(',', $headers));
        
        foreach ($prices as $price) {
            $row = [
                $price['id'] ?? 'N/A',
                '"' . ($price['product_name'] ?? 'Unknown') . '"',
                $price['unit_amount'] ?? 0,
                strtoupper($price['currency'] ?? 'USD'),
                $this->formatInterval($price),
                $price['active'] ? 'Yes' : 'No',
                isset($price['created']) ? date('Y-m-d', $price['created']) : 'N/A',
            ];
            
            $this->line(implode(',', $row));
        }
    }

    /**
     * Format amount for display
     */
    private function formatAmount(int $amount, string $currency): string
    {
        $formatted = number_format($amount / 100, 2);
        return match (strtoupper($currency)) {
            'USD' => '$' . $formatted,
            'EUR' => '€' . $formatted,
            'GBP' => '£' . $formatted,
            default => $formatted . ' ' . strtoupper($currency)
        };
    }

    /**
     * Format billing interval
     */
    private function formatInterval(array $price): string
    {
        if (!isset($price['recurring'])) {
            return 'One-time';
        }
        
        $interval = $price['recurring']['interval'] ?? 'unknown';
        $intervalCount = $price['recurring']['interval_count'] ?? 1;
        
        if ($intervalCount === 1) {
            return ucfirst($interval);
        }
        
        return "Every {$intervalCount} {$interval}s";
    }
}
