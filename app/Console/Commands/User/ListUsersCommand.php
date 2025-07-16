<?php

namespace App\Console\Commands\User;

use App\Console\Commands\BaseCommand;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;

class ListUsersCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:list 
                            {--role= : Filter by role}
                            {--trial= : Filter by trial status (active, expired, none, unlimited)}
                            {--verified : Show only verified users}
                            {--unverified : Show only unverified users}
                            {--limit= : Limit number of results}
                            {--format= : Output format (table, json, csv)}
                            {--export= : Export to file}
                            {--stats : Show user statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List users with various filters and export options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('stats')) {
            return $this->showStats();
        }

        $query = User::with('roles', 'subscriptions');

        // Apply filters
        $this->applyFilters($query);

        // Get results
        $users = $query->get();

        if ($this->option('limit')) {
            $users = $users->take($this->option('limit'));
        }

        // Output results
        $format = $this->option('format') ?: 'table';
        $exportFile = $this->option('export');

        switch ($format) {
            case 'json':
                return $this->outputJson($users, $exportFile);
            case 'csv':
                return $this->outputCsv($users, $exportFile);
            default:
                return $this->outputTable($users, $exportFile);
        }
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters($query): void
    {
        // Role filter
        if ($role = $this->option('role')) {
            $query->role($role);
        }

        // Email verification filter
        if ($this->option('verified')) {
            $query->whereNotNull('email_verified_at');
        }

        if ($this->option('unverified')) {
            $query->whereNull('email_verified_at');
        }

        // Trial filter
        if ($trial = $this->option('trial')) {
            $now = Carbon::now();
            
            switch ($trial) {
                case 'active':
                    $query->whereNotNull('trial_ends_at')
                          ->where('trial_ends_at', '>', $now);
                    break;
                case 'expired':
                    $query->whereNotNull('trial_ends_at')
                          ->where('trial_ends_at', '<', $now);
                    break;
                case 'none':
                    $query->whereNull('trial_ends_at');
                    break;
                case 'unlimited':
                    $query->whereNotNull('trial_ends_at')
                          ->whereYear('trial_ends_at', '>', 2035);
                    break;
            }
        }
    }

    /**
     * Output users as table
     */
    private function outputTable($users, $exportFile = null): int
    {
        if ($users->isEmpty()) {
            $this->warning("No users found matching the criteria.");
            return self::SUCCESS;
        }

        $headers = ['Name', 'Email', 'Roles', 'Trial Status', 'Verified', 'Created'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->name,
                $user->email,
                $user->roles->pluck('name')->implode(', ') ?: 'None',
                $this->getTrialStatus($user),
                $user->email_verified_at ? 'Yes' : 'No',
                $user->created_at->format('Y-m-d'),
            ];
        }

        $this->table($headers, $rows);
        $this->line("Total users: " . $users->count());

        if ($exportFile) {
            $this->exportTable($headers, $rows, $exportFile);
        }

        return self::SUCCESS;
    }

    /**
     * Output users as JSON
     */
    private function outputJson($users, $exportFile = null): int
    {
        $data = $users->map(function ($user) {
            return [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'trial_status' => $this->getTrialStatus($user),
                'email_verified' => $user->email_verified_at ? true : false,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ];
        });

        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        if ($exportFile) {
            // Create directory if it doesn't exist
            $directory = dirname($exportFile);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            file_put_contents($exportFile, $json);
            $this->success("Users exported to {$exportFile}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }

    /**
     * Output users as CSV
     */
    private function outputCsv($users, $exportFile = null): int
    {
        $headers = ['UUID', 'Name', 'Email', 'Roles', 'Trial Status', 'Email Verified', 'Created At'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->uuid,
                $user->name,
                $user->email,
                $user->roles->pluck('name')->implode('; '),
                $this->getTrialStatus($user),
                $user->email_verified_at ? 'Yes' : 'No',
                $user->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $csv = $this->arrayToCsv(array_merge([$headers], $rows));
        
        if ($exportFile) {
            // Create directory if it doesn't exist
            $directory = dirname($exportFile);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            file_put_contents($exportFile, $csv);
            $this->success("Users exported to {$exportFile}");
        } else {
            $this->line($csv);
        }

        return self::SUCCESS;
    }

    /**
     * Show user statistics
     */
    private function showStats(): int
    {
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $unverifiedUsers = $totalUsers - $verifiedUsers;
        
        $now = Carbon::now();
        $activeTrials = User::whereNotNull('trial_ends_at')
                           ->where('trial_ends_at', '>', $now)
                           ->count();
        $expiredTrials = User::whereNotNull('trial_ends_at')
                            ->where('trial_ends_at', '<', $now)
                            ->count();
        $noTrials = User::whereNull('trial_ends_at')->count();
        $unlimitedTrials = User::whereNotNull('trial_ends_at')
                              ->whereYear('trial_ends_at', '>', 2090)
                              ->count();

        $this->line("=== User Statistics ===");
        $this->line("Total Users: {$totalUsers}");
        $this->line("");
        
        $this->line("Email Verification:");
        $this->line("  Verified: {$verifiedUsers}");
        $this->line("  Unverified: {$unverifiedUsers}");
        $this->line("");
        
        $this->line("Trial Status:");
        $this->line("  Active Trials: {$activeTrials}");
        $this->line("  Expired Trials: {$expiredTrials}");
        $this->line("  No Trials: {$noTrials}");
        $this->line("  Unlimited Trials: {$unlimitedTrials}");
        $this->line("");

        // Role statistics
        $roles = Role::withCount('users')->get();
        if ($roles->isNotEmpty()) {
            $this->line("Roles:");
            foreach ($roles as $role) {
                $this->line("  {$role->name}: {$role->users_count} users");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Get trial status for a user
     */
    private function getTrialStatus($user): string
    {
        if (!$user->trial_ends_at) {
            return 'None';
        }

        if ($user->trial_ends_at->year > 2090) {
            return 'Unlimited';
        }

        $now = Carbon::now();
        if ($user->trial_ends_at->isFuture()) {
            $daysLeft = $now->diffInDays($user->trial_ends_at);
            return "Active ({$daysLeft} days)";
        } else {
            $daysAgo = $user->trial_ends_at->diffInDays($now);
            return "Expired ({$daysAgo} days ago)";
        }
    }

    /**
     * Export table to file
     */
    private function exportTable($headers, $rows, $filename): void
    {
        $content = "=== User List Export ===\n\n";
        
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
            foreach ($rows as $row) {
                $widths[$i] = max($widths[$i], strlen($row[$i]));
            }
        }

        // Format header
        $headerLine = '| ';
        foreach ($headers as $i => $header) {
            $headerLine .= str_pad($header, $widths[$i]) . ' | ';
        }
        $content .= $headerLine . "\n";
        $content .= '|' . str_repeat('-', strlen($headerLine) - 2) . "|\n";

        // Format rows
        foreach ($rows as $row) {
            $rowLine = '| ';
            foreach ($row as $i => $cell) {
                $rowLine .= str_pad($cell, $widths[$i]) . ' | ';
            }
            $content .= $rowLine . "\n";
        }

        // Create directory if it doesn't exist
        $directory = dirname($filename);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($filename, $content);
        $this->success("Users exported to {$filename}");
    }

    /**
     * Convert array to CSV
     */
    private function arrayToCsv($array): string
    {
        $output = '';
        foreach ($array as $row) {
            $output .= implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        return $output;
    }
}
