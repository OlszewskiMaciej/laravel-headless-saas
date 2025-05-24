<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Console\Command;

class RevokeApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:revoke
                            {id? : The ID of the API key to revoke}
                            {--force : Permanently delete the key instead of just disabling it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke an API key';

    /**
     * Create a new command instance.
     */
    public function __construct(protected ApiKeyService $apiKeyService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        // If no ID provided, show a list to select from
        if (!$id) {
            $this->info('No API key ID provided. Listing available keys:');
            $this->call('api-key:list');
            
            $id = $this->ask('Enter the ID of the API key to revoke');
        }
        
        // Get the API key
        $apiKey = ApiKey::find($id);
        
        if (!$apiKey) {
            $this->error("API key with ID {$id} not found.");
            return Command::FAILURE;
        }
        
        // Show API key details and confirm
        $this->info('API Key Details:');
        $this->table(['Property', 'Value'], [
            ['ID', $apiKey->id],
            ['Name', $apiKey->name],
            ['Service', $apiKey->service],
            ['Environment', $apiKey->environment],
            ['Active', $apiKey->is_active ? 'Yes' : 'No'],
            ['Created At', $apiKey->created_at->format('Y-m-d H:i:s')],
        ]);
        
        $force = $this->option('force');
        $action = $force ? 'permanently delete' : 'revoke';
        
        if (!$this->confirm("Are you sure you want to {$action} this API key?", false)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }
        
        // Revoke or delete the API key
        if ($force) {
            $this->apiKeyService->deleteKey($apiKey);
            $this->info('API key permanently deleted.');
        } else {
            $this->apiKeyService->revokeKey($apiKey);
            $this->info('API key revoked.');
        }
        
        return Command::SUCCESS;
    }
}
