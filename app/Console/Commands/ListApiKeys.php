<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class ListApiKeys extends Command
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
                            {--show-deleted : Include soft-deleted keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all API keys';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = ApiKey::query();
        
        // Apply filters
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
        
        // Get API keys
        $keys = $query->orderBy('created_at', 'desc')->get();
        
        if ($keys->isEmpty()) {
            $this->info('No API keys found matching the criteria.');
            return Command::SUCCESS;
        }
        
        // Prepare data for table
        $data = [];
        foreach ($keys as $key) {
            $data[] = [
                $key->id,
                $key->name,
                $key->service,
                $key->environment,
                $key->is_active ? 'Yes' : 'No',
                $key->deleted_at ? 'Yes' : 'No',
                $key->expires_at ? $key->expires_at->format('Y-m-d') : 'Never',
                $key->last_used_at ? $key->last_used_at->format('Y-m-d H:i:s') : 'Never',
            ];
        }
        
        // Display table
        $this->table(
            ['ID', 'Name', 'Service', 'Environment', 'Active', 'Deleted', 'Expires At', 'Last Used'],
            $data
        );
        
        return Command::SUCCESS;
    }
}
