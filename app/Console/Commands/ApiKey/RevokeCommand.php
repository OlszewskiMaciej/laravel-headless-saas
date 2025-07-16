<?php

namespace App\Console\Commands\ApiKey;

use App\Console\Commands\BaseCommand;
use App\Models\ApiKey;
use App\Console\Commands\ApiKey\Services\ApiKeyService;

class RevokeCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:revoke
                            {uuid? : The UUID of the API key to revoke}
                            {--force : Permanently delete the key instead of just disabling it}
                            {--all-inactive : Revoke all inactive keys}
                            {--confirm : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke an API key or multiple keys';

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
    public function handle(): int
    {
        try {
            if ($this->option('all-inactive')) {
                return $this->revokeAllInactive();
            }

            $uuid = $this->getApiKeyUuid();
            $apiKey = $this->findApiKey($uuid);

            if (!$apiKey) {
                return self::FAILURE;
            }

            $this->displayApiKeyDetails($apiKey);
            
            if (!$this->confirmRevocation($apiKey)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }

            $this->processRevocation($apiKey);
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Get API key ID from argument or prompt
     */
    private function getApiKeyUuid(): string
    {
        $uuid = $this->argument('uuid');

        if (!$uuid) {
            $this->info('No API key UUID provided. Listing available keys:');
            $this->call('api-key:list');
            $uuid = $this->ask('Enter the UUID of the API key to revoke');
        }

        return $uuid;
    }

    /**
     * Find API key by UUID
     */
    private function findApiKey(string $uuid): ?ApiKey
    {
        $apiKey = ApiKey::find($uuid);
        
        if (!$apiKey) {
            $this->error("API key with UUID {$uuid} not found.");
        }
        
        return $apiKey;
    }

    /**
     * Display API key details
     */
    private function displayApiKeyDetails(ApiKey $apiKey): void
    {
        $this->info('API Key Details:');
        $this->table(['Property', 'Value'], [
            ['UUID', $apiKey->uuid],
            ['Name', $apiKey->name],
            ['Service', $apiKey->service],
            ['Environment', $apiKey->environment],
            ['Status', $apiKey->is_active ? 'Active' : 'Inactive'],
            ['Expires', $apiKey->expires_at ? $apiKey->expires_at->format('Y-m-d H:i:s') : 'Never'],
            ['Created', $apiKey->created_at->format('Y-m-d H:i:s')],
            ['Last Used', $apiKey->last_used_at ? $apiKey->last_used_at->format('Y-m-d H:i:s') : 'Never'],
        ]);
    }

    /**
     * Confirm the action with user
     */
    private function confirmRevocation(ApiKey $apiKey): bool
    {
        if ($this->option('confirm')) {
            return true;
        }

        $force = $this->option('force');
        $action = $force ? 'permanently delete' : 'revoke';
        
        return $this->confirm("Are you sure you want to {$action} this API key?", false);
    }

    /**
     * Process the revocation
     */
    private function processRevocation(ApiKey $apiKey): void
    {
        $force = $this->option('force');
        
        if ($force) {
            $this->apiKeyService->deleteKey($apiKey);
            $this->info('API key permanently deleted.');
        } else {
            $this->apiKeyService->revokeKey($apiKey);
            $this->info('API key revoked successfully.');
        }
    }

    /**
     * Revoke all inactive API keys
     */
    private function revokeAllInactive(): int
    {
        $inactiveKeys = ApiKey::where('is_active', false)->get();
        
        if ($inactiveKeys->isEmpty()) {
            $this->info('No inactive API keys found.');
            return self::SUCCESS;
        }

        $this->info("Found {$inactiveKeys->count()} inactive API key(s).");
        
        if (!$this->option('confirm') && !$this->confirm('Proceed with revocation?', false)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($inactiveKeys as $key) {
            $this->apiKeyService->revokeKey($key);
            $count++;
        }

        $this->info("Successfully revoked {$count} inactive API key(s).");
        return self::SUCCESS;
    }
}
