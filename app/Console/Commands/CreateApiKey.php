<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateApiKey extends Command
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
    public function handle()
    {
        // Get or prompt for required options
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
        
        // Handle expiration
        $expiresAt = null;
        $expires = $this->option('expires');
        if ($expires === null) {
            $shouldExpire = $this->confirm('Should the API key expire?', false);
            if ($shouldExpire) {
                $days = $this->ask('Days until expiration', 365);
                $expiresAt = now()->addDays((int)$days);
            }
        } elseif ((int)$expires > 0) {
            $expiresAt = now()->addDays((int)$expires);
        }

        // Create the API key
        $result = $this->apiKeyService->createKey(
            $name,
            $service,
            $environment,
            $description,
            $expiresAt
        );

        // Display the result
        $this->info('API key created successfully!');
        $this->warn('Please store this API key safely, as it won\'t be displayed again:');
        $this->newLine();
        $this->line($result['plain_text_key']);
        $this->newLine();
        
        // Display key details
        $this->table(['Property', 'Value'], [
            ['ID', $result['api_key']->id],
            ['Name', $result['api_key']->name],
            ['Service', $result['api_key']->service],
            ['Environment', $result['api_key']->environment],
            ['Expires At', $result['api_key']->expires_at ? $result['api_key']->expires_at->format('Y-m-d H:i:s') : 'Never'],
            ['Created At', $result['api_key']->created_at->format('Y-m-d H:i:s')],
        ]);

        return Command::SUCCESS;
    }
}
