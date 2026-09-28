<?php

namespace App\Console\Commands;

use App\Services\FinderSiteImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bootstraps Service Finder coverage from an operations site export.
 *
 * The same importer backs the admin Import Runner, so the CLI and the UI can
 * never drift. Safe to re-run: rows are matched on a fingerprint, and
 * anything an operator has edited is left alone.
 *
 *   php artisan finder:import-sites --dry-run
 *   php artisan finder:import-sites
 *   php artisan finder:import-sites --force        # rebuild imported rows
 */
class FinderImportSites extends Command
{
    protected $signature = 'finder:import-sites
        {--file= : Absolute path to the CSV (defaults to the configured file)}
        {--dry-run : Classify and report without writing or geocoding}
        {--force : Replace previously imported rows (admin-edited rows are kept)}
        {--skip-geocode : Import without resolving coordinates}';

    protected $description = 'Import operational sites and rebuild Service Finder coverage areas.';

    public function handle(FinderSiteImporter $importer): int
    {
        $path = $this->option('file') ?: config('service_finder.import.default_file');
        $path = str_replace('\\', '/', (string) $path);

        $this->line("Reading <info>{$path}</info>");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing will be written and no geocoding calls will be made.');
        }

        try {
            $report = $importer->import($path, [
                'dry_run' => (bool) $this->option('dry-run'),
                'force' => (bool) $this->option('force'),
                'skip_geocode' => (bool) $this->option('skip-geocode'),
            ]);
        } catch (Throwable $exception) {
            $this->error('Import failed: '.$exception->getMessage());
            report($exception);

            return self::FAILURE;
        }

        $this->renderReport($report);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    protected function renderReport(array $report): void
    {
        $this->newLine();
        $this->line('<comment>Rows</comment>');
        $this->table(
            ['Metric', 'Count'],
            collect($report['counts'])
                ->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])
                ->values()
                ->all()
        );

        if (! empty($report['inactive_reasons'])) {
            $this->line('<comment>Held back (imported but hidden from the map)</comment>');
            $this->table(
                ['Reason', 'Count'],
                collect($report['inactive_reasons'])
                    ->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])
                    ->values()
                    ->all()
            );
        }

        if (! empty($report['geocoding'])) {
            $this->line('<comment>Geocoding</comment>');
            $this->table(
                ['Source', 'Lookups'],
                collect($report['geocoding'])
                    ->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])
                    ->values()
                    ->all()
            );
        }

        if (! empty($report['sample'])) {
            $this->line('<comment>Sample</comment>');
            $this->table(['Postcode', 'Area', 'Active', 'Reason'], $report['sample']);
        }

        $this->newLine();
        $this->info($report['dry_run']
            ? '✓ Dry run complete — re-run without --dry-run to apply.'
            : '✓ Import complete and coverage areas rebuilt.');
    }
}
