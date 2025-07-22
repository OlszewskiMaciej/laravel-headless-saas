<?php

namespace App\Console\Commands\ApiKey;

use App\Console\Commands\BaseCommand;
use App\Models\ApiKey;

class ListCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:list
                            {--service= : Filter by service}
                            {--environment= : Filter by environment}
                            {--show-inactive : Include inactive keys}
                            {--show-deleted : Include soft-deleted keys}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all API keys';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keys = $this->getFilteredApiKeys();

        if ($keys->isEmpty()) {
            $this->info('No API keys found matching the criteria.');
            return self::SUCCESS;
        }

        $this->displayKeys($keys);
        return self::SUCCESS;
    }

    /**
     * Get filtered API keys based on options
     */
    private function getFilteredApiKeys()
    {
        $query = ApiKey::query();

        if ($service = $this->option('service')) {
            $query->where('service', $service);
        }

        if ($environment = $this->option('environment')) {
            $query->where('environment', $environment);
        }

        if (!$this->option('show-inactive')) {
            $query->where('is_active', true);
        }

        if ($this->option('show-deleted')) {
            $query->withTrashed();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Display API keys in requested format
     */
    private function displayKeys($keys): void
    {
        $format = $this->option('format');

        match ($format) {
            'json'  => $this->displayAsJson($keys),
            default => $this->displayAsTable($keys)
        };
    }

    /**
     * Display API keys as table
     */
    private function displayAsTable($keys): void
    {
        $data = $keys->map(function ($key) {
            return [
                $key->uuid,
                $key->name,
                $key->service,
                $key->environment,
                $key->is_active ? '✓' : '✗',
                $key->deleted_at ? '✓' : '✗',
                $key->expires_at ? $key->expires_at->format('Y-m-d') : 'Never',
                $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never',
            ];
        })->toArray();

        $this->table(
            ['UUID', 'Name', 'Service', 'Environment', 'Active', 'Deleted', 'Expires', 'Last Used'],
            $data
        );
    }

    /**
     * Display API keys as JSON
     */
    private function displayAsJson($keys): void
    {
        $this->line($keys->toJson(JSON_PRETTY_PRINT));
    }
}
