<?php

namespace App\Console\Commands\ApiKey;

use App\Console\Commands\BaseCommand;
use App\Services\ApiKeyService;

class CreateCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:create
                            {--name= : Name of the API key}
                            {--service= : Service the API key is for (e.g., web-frontend, mobile-app)}
                            {--environment= : Environment the API key is for (e.g., development, production)}
                            {--description= : Optional description of the API key}
                            {--expires= : Number of days until the API key expires (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new API key for service authentication';

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
            $keyData = $this->gatherKeyData();
            $result = $this->createApiKey($keyData);
            $this->displaySuccess($result);
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create API key: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Gather API key data from options or prompts
     */
    private function gatherKeyData(): array
    {
        $name = $this->option('name') ?: $this->ask('Name for the API key');
        $service = $this->option('service') ?: $this->anticipate(
            'Service the API key is for',
            ['web-frontend', 'mobile-app', 'admin-dashboard', 'third-party-integration']
        );
        $environment = $this->option('environment') ?: $this->choice(
            'Environment the API key is for',
            ['development', 'testing', 'staging', 'production'],
            0
        );
        $description = $this->option('description') ?: $this->ask('Description (optional)', null);
        
        return [
            'name' => $name,
            'service' => $service,
            'environment' => $environment,
            'description' => $description,
            'expires_at' => $this->determineExpiration()
        ];
    }

    /**
     * Determine expiration date for the API key
     */
    private function determineExpiration(): ?\Carbon\Carbon
    {
        $expires = $this->option('expires');
        
        if ($expires === null) {
            $shouldExpire = $this->confirm('Should the API key expire?', false);
            if ($shouldExpire) {
                $days = $this->ask('Days until expiration', 365);
                return now()->addDays((int)$days);
            }
            return null;
        }
        
        return (int)$expires > 0 ? now()->addDays((int)$expires) : null;
    }

    /**
     * Create the API key
     */
    private function createApiKey(array $keyData): array
    {
        return $this->apiKeyService->createKey(
            $keyData['name'],
            $keyData['service'],
            $keyData['environment'],
            $keyData['description'],
            $keyData['expires_at']
        );
    }

    /**
     * Display success message and key details
     */
    private function displaySuccess(array $result): void
    {
        $this->success('API key created successfully!');
        $this->warning('Please store this API key safely, as it won\'t be displayed again:');
        $this->newLine();
        $this->line('<fg=yellow>' . $result['plain_text_key'] . '</>');
        $this->newLine();
        
        $this->table(['Property', 'Value'], [
            ['ID', $result['api_key']->id],
            ['Name', $result['api_key']->name],
            ['Service', $result['api_key']->service],
            ['Environment', $result['api_key']->environment],
            ['Expires At', $result['api_key']->expires_at ? $result['api_key']->expires_at->format('Y-m-d H:i:s') : 'Never'],
            ['Created At', $result['api_key']->created_at->format('Y-m-d H:i:s')],
        ]);
    }
}
