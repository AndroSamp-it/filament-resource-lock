<?php

namespace Androsamp\FilamentResourceLock\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallFilamentResourceLockCommand extends Command
{
    protected $signature = 'filament-resource-lock:install
                            {--force : Overwrite existing published files}';

    protected $description = 'Publish config, migration, and JS assets; inject Echo import into bootstrap.js';

    public function handle(): int
    {
        $this->components->info('Publishing config…');
        Artisan::call('vendor:publish', [
            '--tag' => 'filament-resource-lock-config',
            '--force' => $this->option('force'),
        ]);
        $this->output->write(Artisan::output());

        $this->components->info('Publishing migration…');
        Artisan::call('vendor:publish', [
            '--tag' => 'filament-resource-lock-migrations',
            '--force' => $this->option('force'),
        ]);
        $this->output->write(Artisan::output());

        $this->components->info('Publishing JS assets…');
        Artisan::call('vendor:publish', [
            '--tag' => 'filament-resource-lock-assets',
            '--force' => $this->option('force'),
        ]);
        $this->output->write(Artisan::output());

        $this->injectBootstrapImport();

        $this->components->info('Run `php artisan migrate` to create the resource locks table.');

        return self::SUCCESS;
    }

    private function injectBootstrapImport(): void
    {
        $bootstrapPath = resource_path('js/bootstrap.js');
        $importLine = "import './filament-resource-lock/echo';";

        if (! file_exists($bootstrapPath)) {
            $this->components->warn('bootstrap.js not found, skipping import injection.');
            return;
        }

        $contents = file_get_contents($bootstrapPath);

        if (str_contains($contents, $importLine)) {
            $this->components->twoColumnDetail('bootstrap.js', '<fg=yellow;options=bold>already contains Echo import</>');
            return;
        }

        file_put_contents($bootstrapPath, $contents . PHP_EOL . $importLine . PHP_EOL);

        $this->components->twoColumnDetail('bootstrap.js', '<fg=green;options=bold>Echo import injected</>');
    }
}
