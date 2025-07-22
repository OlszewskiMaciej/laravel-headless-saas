<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

abstract class BaseCommand extends Command
{
    /**
     * Display a success message with consistent formatting
     */
    protected function success(string $message): void
    {
        $this->info("✓ {$message}");
    }

    /**
     * Display a warning message with consistent formatting
     */
    protected function warning(string $message): void
    {
        $this->warn("⚠ {$message}");
    }

    /**
     * Display an error message with consistent formatting
     */
    protected function failure(string $message): void
    {
        $this->error("✗ {$message}");
    }

    /**
     * Display a step message with consistent formatting
     */
    protected function step(string $message): void
    {
        $this->line("→ {$message}");
    }

    /**
     * Display a section header
     */
    protected function section(string $title): void
    {
        $this->newLine();
        $this->line('<fg=cyan>' . str_repeat('=', strlen($title)) . '</>');
        $this->line("<fg=cyan>{$title}</>");
        $this->line('<fg=cyan>' . str_repeat('=', strlen($title)) . '</>');
    }

    /**
     * Confirm action with user, respecting --force option
     */
    protected function confirmAction(string $message, bool $default = false): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm($message, $default);
    }

    /**
     * Display a summary table
     */
    protected function summary(array $data): void
    {
        $this->newLine();
        $this->info('Summary:');

        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [ucfirst(str_replace('_', ' ', $key)), $value];
        }

        $this->table(['Metric', 'Value'], $rows);
    }

    /**
     * Handle command execution with error handling
     */
    protected function executeWithErrorHandling(callable $callback): int
    {
        try {
            return $callback() ?? self::SUCCESS;
        } catch (\Exception $e) {
            $this->failure("Command failed: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->line('<fg=red>Stack trace:</>');
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Display progress for batch operations
     */
    protected function withProgress(int $total, callable $callback): void
    {
        $progress = $this->output->createProgressBar($total);
        $progress->start();

        try {
            $callback($progress);
        } finally {
            $progress->finish();
            $this->newLine();
        }
    }
}
