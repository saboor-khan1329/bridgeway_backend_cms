<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\AdminFileManagerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Finds files on the public disk that nothing in the database points at, and
 * (only when asked) archives then removes them.
 *
 * Why a dedicated command: uploads outlive their rows. Replacing an image,
 * deleting a record before the purge hook existed, or an import that wrote
 * files then failed all leave bytes behind that no page will ever render.
 * Those are invisible in the admin file manager's per-folder view and only
 * show up as a storage bill.
 *
 * Safety model — nothing is destroyed without a restorable copy:
 *   - Dry run is the DEFAULT. Deleting requires --force, explicitly.
 *   - --force still archives every candidate to a .tar.gz FIRST, verifies the
 *     archive lists the expected number of entries, and only then deletes.
 *     A failed or short archive aborts the run with nothing removed.
 *   - Files newer than --min-age-days (default 1) are always skipped, so an
 *     upload that is mid-request and not yet committed to a row is never
 *     mistaken for an orphan.
 *   - Every run writes a JSON report (archive path, timestamp, counts, the
 *     full file list with sizes) so a delete can be traced and undone.
 *
 *   php artisan images:cleanup-orphans                 # dry run + report
 *   php artisan images:cleanup-orphans --force         # archive, then delete
 *   php artisan images:cleanup-orphans --min-age-days=7
 */
class ImagesCleanupOrphans extends Command
{
    protected $signature = 'images:cleanup-orphans
        {--force            : Actually archive and delete (default is a dry run)}
        {--disk=            : Disk to scan (default: admin file manager disk)}
        {--min-age-days=1   : Ignore files modified within this many days}
        {--archive-dir=     : Where to write the .tar.gz (default: storage/app/backups/orphan-images)}
        {--report=          : Full path for the JSON report (default: alongside the archive)}
        {--limit=0          : Only act on the first N orphans (0 = all)}
        {--show=25          : How many rows to print in the console table}';

    protected $description = 'Report (and optionally archive + delete) unreferenced files on the public disk.';

    public function handle(AdminFileManagerService $files): int
    {
        $diskName = (string) ($this->option('disk') ?: $files->diskName());
        $disk = Storage::disk($diskName);
        $minAgeDays = max(0, (int) $this->option('min-age-days'));
        $cutoff = now()->subDays($minAgeDays)->getTimestamp();

        $this->line('');
        $this->line('  <fg=cyan>Orphaned asset scan</>');
        $this->line('  Disk        : ' . $diskName);
        $this->line('  Root        : ' . $disk->path(''));
        $this->line('  Min age     : ' . $minAgeDays . ' day(s)');
        $this->line('  Mode        : ' . ($this->option('force') ? '<fg=red>DELETE (archive first)</>' : '<fg=green>DRY RUN</>'));
        $this->line('');

        $referenced = $this->referencedPaths($diskName, $files);
        $this->line('  Referenced in DB : ' . count($referenced));

        [$orphans, $skippedRecent, $totalScanned] = $this->findOrphans($disk, $referenced, $cutoff, $files);

        $this->line('  Files on disk    : ' . $totalScanned);
        $this->line('  Too recent, kept : ' . $skippedRecent);
        $this->line('  <fg=yellow>Orphans found    : ' . count($orphans) . '</>');

        if ($limit = (int) $this->option('limit')) {
            $orphans = array_slice($orphans, 0, $limit);
            $this->line('  Limited to       : ' . count($orphans));
        }

        $bytes = array_sum(array_column($orphans, 'size_bytes'));
        $this->line('  Reclaimable      : ' . $this->humanSize($bytes));
        $this->line('');

        if (empty($orphans)) {
            $this->info('  Nothing to clean — every file on the disk is referenced.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->renderTable($orphans);

        $archiveDir = (string) ($this->option('archive-dir')
            ?: storage_path('app/backups/orphan-images'));
        $stamp = now()->format('Ymd-His');
        $archivePath = $archiveDir . '/orphan-images-' . $stamp . '.tar.gz';
        $reportPath = (string) ($this->option('report')
            ?: $archiveDir . '/orphan-images-' . $stamp . '.json');

        // ── Dry run: report only ────────────────────────────────────────────
        if (! $this->option('force')) {
            $this->writeReport($reportPath, [
                'mode' => 'dry-run',
                'archive' => null,
                'deleted' => false,
            ], $orphans, $diskName, $minAgeDays, $totalScanned, count($referenced), $skippedRecent);

            $this->line('');
            $this->warn('  DRY RUN — nothing was archived or deleted.');
            $this->line('  Report written: <info>' . $reportPath . '</info>');
            $this->line('');
            $this->line('  To archive and delete these files, re-run with --force:');
            $this->line('    <info>php artisan images:cleanup-orphans --force</info>');
            $this->line('');

            return self::SUCCESS;
        }

        // ── Archive BEFORE deleting ─────────────────────────────────────────
        File::ensureDirectoryExists($archiveDir);
        $this->line('  Archiving ' . count($orphans) . ' file(s)...');

        if (! $this->archive($disk, $orphans, $archivePath)) {
            $this->error('  Archive failed — nothing was deleted.');

            return self::FAILURE;
        }

        $entries = $this->archiveEntryCount($archivePath);

        if ($entries < count($orphans)) {
            $this->error(sprintf(
                '  Archive holds %d of %d expected file(s) — aborting, nothing deleted. Archive kept at: %s',
                $entries,
                count($orphans),
                $archivePath
            ));

            return self::FAILURE;
        }

        $this->info('  Archive verified: ' . $archivePath
            . ' (' . $this->humanSize(filesize($archivePath)) . ', ' . $entries . ' entries)');

        // ── Delete ──────────────────────────────────────────────────────────
        $deleted = 0;
        $failed = [];

        foreach ($orphans as $orphan) {
            if ($disk->delete($orphan['path'])) {
                $deleted++;
            } else {
                $failed[] = $orphan['path'];
            }
        }

        $this->writeReport($reportPath, [
            'mode' => 'force',
            'archive' => $archivePath,
            'deleted' => true,
            'deleted_count' => $deleted,
            'failed' => $failed,
        ], $orphans, $diskName, $minAgeDays, $totalScanned, count($referenced), $skippedRecent);

        $this->line('');
        $this->info('  Deleted ' . $deleted . ' file(s), reclaimed ' . $this->humanSize($bytes));

        if ($failed) {
            $this->warn('  ' . count($failed) . ' file(s) could not be deleted (see report).');
        }

        $this->line('  Archive : <info>' . $archivePath . '</info>');
        $this->line('  Report  : <info>' . $reportPath . '</info>');
        $this->line('');
        $this->line('  To restore everything from this archive:');
        $this->line('    <info>tar -xzf ' . $archivePath . ' -C ' . $disk->path('') . '</info>');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Every disk path the database points at.
     *
     * images.path is the authority (same lookup AdminFileManagerService uses
     * before it deletes a replaced upload), plus the file-tracking tables that
     * store their own paths. Anything listed here is never a deletion
     * candidate, whatever folder it sits in.
     *
     * @return array<string, true> normalised path => true, for O(1) lookups
     */
    protected function referencedPaths(string $diskName, AdminFileManagerService $files): array
    {
        $referenced = [];

        $add = function (?string $path) use (&$referenced, $files): void {
            $path = $files->normalizePath($path);

            if ($path !== '') {
                $referenced[$path] = true;
            }
        };

        Image::query()
            ->where('disk', $diskName)
            ->orderBy('id')
            ->pluck('path')
            ->each($add);

        // Other tables that persist a path of their own. Guarded by table and
        // column existence so this keeps working if a feature is dropped.
        $extra = [
            ['excel_exports', 'stored_path'],
            ['excel_imports', 'stored_path'],
            ['excel_imports', 'error_report_path'],
            ['onboarding_submission_files', 'file_path'],
        ];

        foreach ($extra as [$table, $column]) {
            if (! $this->columnExists($table, $column)) {
                continue;
            }

            DB::table($table)->orderBy('id')->pluck($column)->each($add);
        }

        return $referenced;
    }

    /**
     * Walk the disk and collect what nothing references.
     *
     * @param  array<string, true>  $referenced
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: int}
     */
    protected function findOrphans($disk, array $referenced, int $cutoff, AdminFileManagerService $files): array
    {
        $orphans = [];
        $skippedRecent = 0;
        $scanned = 0;

        foreach ($disk->allFiles() as $path) {
            $normalised = $files->normalizePath($path);

            if ($normalised === '') {
                continue;
            }

            $scanned++;

            if (isset($referenced[$normalised])) {
                continue;
            }

            $modified = (int) $disk->lastModified($normalised);

            // A file that is still warm may belong to a row being written in
            // another request. Leave it for the next run.
            if ($modified > $cutoff) {
                $skippedRecent++;

                continue;
            }

            $orphans[] = [
                'path' => $normalised,
                'size_bytes' => (int) $disk->size($normalised),
                'modified_at' => date('c', $modified),
            ];
        }

        usort($orphans, fn ($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);

        return [$orphans, $skippedRecent, $scanned];
    }

    /**
     * tar the candidates, preserving their paths relative to the disk root so
     * a restore is a single tar -xzf back into the same place.
     *
     * @param  array<int, array<string, mixed>>  $orphans
     */
    protected function archive($disk, array $orphans, string $archivePath): bool
    {
        $root = rtrim($disk->path(''), '/');

        // -T reads the file list from a manifest, which avoids both the
        // shell's argument-length limit and any quoting surprises in names.
        $manifest = tempnam(sys_get_temp_dir(), 'orphans_');

        if ($manifest === false) {
            return false;
        }

        File::put($manifest, implode("\n", array_column($orphans, 'path')) . "\n");

        $process = new Process([
            'tar', '-czf', $archivePath,
            '-C', $root,
            '--files-from', $manifest,
        ]);
        $process->setTimeout(1800);
        $process->run();

        @unlink($manifest);

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput()) ?: 'tar failed.');
            @unlink($archivePath);

            return false;
        }

        return is_file($archivePath) && filesize($archivePath) > 0;
    }

    protected function archiveEntryCount(string $archivePath): int
    {
        $process = new Process(['tar', '-tzf', $archivePath]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            return 0;
        }

        $lines = array_filter(
            explode("\n", trim($process->getOutput())),
            // Directory entries are not files we archived.
            fn ($line) => $line !== '' && ! str_ends_with($line, '/')
        );

        return count($lines);
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<int, array<string, mixed>>  $orphans
     */
    protected function writeReport(
        string $reportPath,
        array $outcome,
        array $orphans,
        string $diskName,
        int $minAgeDays,
        int $totalScanned,
        int $referencedCount,
        int $skippedRecent
    ): void {
        File::ensureDirectoryExists(dirname($reportPath));

        $report = [
            'generated_at' => now()->toIso8601String(),
            'command' => 'images:cleanup-orphans',
            'environment' => app()->environment(),
            'disk' => $diskName,
            'min_age_days' => $minAgeDays,
            'summary' => [
                'files_scanned' => $totalScanned,
                'referenced_in_db' => $referencedCount,
                'skipped_too_recent' => $skippedRecent,
                'orphans_found' => count($orphans),
                'orphan_bytes' => array_sum(array_column($orphans, 'size_bytes')),
                'orphan_bytes_human' => $this->humanSize(array_sum(array_column($orphans, 'size_bytes'))),
            ],
            'outcome' => $outcome,
            'files' => $orphans,
        ];

        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** @param array<int, array<string, mixed>> $orphans */
    protected function renderTable(array $orphans): void
    {
        $show = max(0, (int) $this->option('show'));

        if ($show === 0) {
            return;
        }

        $rows = array_map(
            fn ($o) => [$this->humanSize($o['size_bytes']), substr($o['modified_at'], 0, 10), $o['path']],
            array_slice($orphans, 0, $show)
        );

        $this->table(['Size', 'Modified', 'Path (largest first)'], $rows);

        if (count($orphans) > $show) {
            $this->line('  ... and ' . (count($orphans) - $show) . ' more — full list is in the JSON report.');
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasTable($table)
            && DB::getSchemaBuilder()->hasColumn($table, $column);
    }

    protected function humanSize(int|false $bytes): string
    {
        if ($bytes === false || $bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
