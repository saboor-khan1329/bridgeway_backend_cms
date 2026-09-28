<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DatabaseRestoreCommand extends Command
{
    protected $signature = 'db:restore
        {file?             : Backup filename or "latest" (omit to list available backups)}
        {--connection=     : Database connection name (default: pgsql)}
        {--force           : Skip confirmation prompt}
        {--migrate         : Run php artisan migrate after restore}
        {--with-storage=   : Also restore a storage archive (filename or "latest")}';

    protected $description = 'Restore a database backup from storage/app/backups/database/.';

    protected string $backupDir;

    public function handle(): int
    {
        $this->backupDir = storage_path('app/backups/database');

        $file = $this->argument('file');

        // No argument — show list
        if (! $file) {
            return $this->listBackups();
        }

        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $config     = config("database.connections.{$connection}");

        if (! is_array($config)) {
            $this->error("Database connection [{$connection}] is not configured.");
            return self::FAILURE;
        }

        // For pgsql: optionally bypass a pooler for direct psql restore.
        if (($config['driver'] ?? '') === 'pgsql') {
            $config = $this->pgsqlDirectConfig($config);
        }

        $path = $this->resolveDumpPath((string) $file);

        if (! $path) {
            return self::FAILURE;
        }

        $size = $this->humanSize(filesize($path));

        $this->line('');
        $this->line('  <fg=cyan>'.config('app.name', 'Bridgeway Digital CMS').' - Database Restore</>');
        $this->line('  File       : ' . basename($path) . " ({$size})");
        $this->line('  Connection : ' . $connection);
        $this->line('  Database   : ' . $config['database']);
        $this->line('  Host:Port  : ' . $config['host'] . ':' . $config['port']);
        $this->line('');
        $this->warn('  WARNING: All current data in [' . $config['database'] . '] will be overwritten.');
        $this->line('');

        if (! $this->option('force') && ! $this->confirm('  Continue?', false)) {
            $this->line('  Restore cancelled.');
            return self::SUCCESS;
        }

        $result = match ($config['driver']) {
            'pgsql'  => $this->restorePgsql($config, $path),
            'mysql'  => $this->restoreMysql($config, $path),
            'sqlite' => $this->restoreSqlite($config, $path),
            default  => $this->unsupported($config['driver'] ?? 'unknown'),
        };

        if ($result !== self::SUCCESS) {
            return $result;
        }

        // Optional: restore storage files
        $storageArg = $this->option('with-storage');
        if ($storageArg !== null) {
            $this->restoreStorage((string) $storageArg);
        }

        // Optional: run pending migrations after restore
        if ($this->option('migrate')) {
            $this->line('');
            $this->line('  Running pending migrations...');
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true], $this->output);
        }

        // Clear caches so restored config/routes take effect
        $this->line('');
        $this->line('  Clearing caches...');
        Artisan::call('optimize:clear', [], $this->output);

        $this->line('');
        $this->info('  Restore complete.');
        $this->line('');

        return self::SUCCESS;
    }

    // ── Drivers ────────────────────────────────────────────────────────────────

    protected function restorePgsql(array $config, string $path): int
    {
        $this->line('  Restoring (this may take a moment)...');

        // Decompress to a temp file first, then feed into psql.
        $tmp = $this->decompressBackupToTempFile($path);

        if (! $tmp) {
            $this->error('Failed to decompress backup.');
            return self::FAILURE;
        }

        $psql = new Process([
            'psql',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-p', (string) ($config['port'] ?? 5432),
            '-U', (string) ($config['username'] ?? ''),
            '-d', (string) ($config['database'] ?? ''),
            '--single-transaction',
            '-v', 'ON_ERROR_STOP=1',
            '-q',
            '-f', $tmp,
        ]);
        $psql->setEnv(['PGPASSWORD' => (string) ($config['password'] ?? '')]);
        $psql->setTimeout(600);
        $psql->run();

        @unlink($tmp);

        if (! $psql->isSuccessful()) {
            $this->error($psql->getErrorOutput() ?: 'Database restore failed.');
            return self::FAILURE;
        }

        $this->info('  Database restored successfully.');
        return self::SUCCESS;
    }

    protected function restoreMysql(array $config, string $path): int
    {
        $this->line('  Restoring (this may take a moment)...');

        $tmp = $this->decompressBackupToTempFile($path);

        if (! $tmp) {
            $this->error('Failed to decompress backup.');
            return self::FAILURE;
        }

        $mysql = new Process([
            'mysql',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? 3306),
            '-u', (string) ($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ]);
        $mysql->setEnv(['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $mysql->setInput(fopen($tmp, 'rb'));
        $mysql->setTimeout(600);
        $mysql->run();

        @unlink($tmp);

        if (! $mysql->isSuccessful()) {
            $this->error($mysql->getErrorOutput() ?: 'MySQL restore failed.');
            return self::FAILURE;
        }

        $this->info('  Database restored successfully.');
        return self::SUCCESS;
    }

    protected function restoreSqlite(array $config, string $path): int
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '') {
            $this->error('SQLite database path is not configured.');
            return self::FAILURE;
        }

        $tmp = $this->decompressBackupToTempFile($path);

        if (! $tmp) {
            $this->error('Failed to decompress backup.');
            return self::FAILURE;
        }

        File::copy($tmp, $database);
        @unlink($tmp);

        $this->info('  SQLite database restored from: ' . basename($path));
        return self::SUCCESS;
    }

    protected function decompressBackupToTempFile(string $path): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dbrestore_');

        if ($tmp === false) {
            return null;
        }

        $source = gzopen($path, 'rb');
        $target = fopen($tmp, 'wb');

        if (! $source || ! $target) {
            if (is_resource($source)) {
                gzclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            @unlink($tmp);

            return null;
        }

        while (! gzeof($source)) {
            $chunk = gzread($source, 1024 * 1024);

            if ($chunk === false || fwrite($target, $chunk) === false) {
                gzclose($source);
                fclose($target);
                @unlink($tmp);

                return null;
            }
        }

        gzclose($source);
        fclose($target);

        return $tmp;
    }

    // ── Storage restore ────────────────────────────────────────────────────────

    protected function restoreStorage(string $arg): void
    {
        $storageDir = storage_path('app/backups/storage');

        if ($arg === 'latest' || $arg === '') {
            $files = glob($storageDir . '/*.tar.gz') ?: [];
            if (empty($files)) {
                $this->warn('  No storage archives found in: ' . $storageDir);
                return;
            }
            usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));
            $path = $files[0];
        } else {
            $path = $this->isAbsolutePath($arg) ? $arg : $storageDir . '/' . $arg;
        }

        if (! file_exists($path)) {
            $this->warn('  Storage archive not found: ' . $path);
            return;
        }

        $dst = storage_path('app/public');
        File::ensureDirectoryExists($dst);

        $this->line('  Restoring storage files from ' . basename($path) . '...');
        $process = new Process(['tar', '-xzf', $path, '-C', $dst]);
        $process->setTimeout(300);
        $process->run();

        if ($process->isSuccessful()) {
            $count = iterator_count(
                new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dst, \FilesystemIterator::SKIP_DOTS))
            );
            $this->info("  Storage restored: {$count} file(s) → {$dst}");
        } else {
            $this->warn('  Storage restore failed: ' . $process->getErrorOutput());
        }
    }

    // ── List ───────────────────────────────────────────────────────────────────

    protected function listBackups(): int
    {
        $files = $this->getBackupFiles();

        if (empty($files)) {
            $this->warn('No backups found in: ' . $this->backupDir);
            $this->line('Create one: php artisan db:backup');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('  <fg=cyan>Available backups:</> ' . $this->backupDir);
        $this->line('');

        $i = 1;
        foreach ($files as $file) {
            $size = $this->humanSize(filesize($file));
            $date = date('Y-m-d H:i:s', filemtime($file));
            $this->line(sprintf('  [%d] %-8s  %s  %s', $i++, $size, $date, basename($file)));
        }

        $this->line('');
        $this->line('  php artisan db:restore <filename>');
        $this->line('  php artisan db:restore latest');
        $this->line('');

        return self::SUCCESS;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    protected function resolveDumpPath(string $file): ?string
    {
        if ($file === 'latest') {
            $files = $this->getBackupFiles();
            if (empty($files)) {
                $this->error('No backups found. Run: php artisan db:backup');
                return null;
            }
            return $files[0];
        }

        $path = $this->isAbsolutePath($file) ? $file : $this->backupDir . '/' . $file;

        if (! File::exists($path)) {
            $this->error("Backup file not found: {$path}");
            $this->line('Run php artisan db:restore (no arguments) to list available backups.');
            return null;
        }

        return $path;
    }

    protected function pgsqlDirectConfig(array $config): array
    {
        $directPort = env('DB_DIRECT_PORT');
        $directDb   = env('DB_DIRECT_DATABASE');

        if ($directPort) {
            $config['port'] = $directPort;
        }

        if ($directDb) {
            $config['database'] = $directDb;
        }

        return $config;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    protected function getBackupFiles(): array
    {
        if (! is_dir($this->backupDir)) {
            return [];
        }

        $files = glob($this->backupDir . '/*.sql.gz') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return $files;
    }

    protected function humanSize(int|false $bytes): string
    {
        if ($bytes === false || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }

    protected function unsupported(string $driver): int
    {
        $this->error("Driver [{$driver}] is not supported by db:restore.");
        return self::FAILURE;
    }
}
