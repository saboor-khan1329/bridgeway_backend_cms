<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
        {--connection=   : Database connection name (default: pgsql)}
        {--path=         : Full output path (overrides default naming)}
        {--with-storage  : Also archive storage/app/public files}
        {--keep=7        : Number of recent backups to retain (0 = keep all)}
        {--list          : List existing backups and exit}';

    protected $description = 'Backup the database to storage/app/backups/database/ (gzip-compressed).';

    protected string $backupDir;

    public function handle(): int
    {
        $this->backupDir = storage_path('app/backups/database');

        if ($this->option('list')) {
            return $this->listBackups();
        }

        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $config     = config("database.connections.{$connection}");

        if (! is_array($config)) {
            $this->error("Database connection [{$connection}] is not configured.");
            return self::FAILURE;
        }

        // For pgsql: optionally override port and database name for a direct
        // pg_dump connection when an environment uses a pooler in front of
        // PostgreSQL.
        if (($config['driver'] ?? '') === 'pgsql') {
            $config = $this->pgsqlDirectConfig($config);
        }

        $path = $this->option('path')
            ? (string) $this->option('path')
            : $this->defaultPath($connection);

        File::ensureDirectoryExists(dirname($path));

        if (File::exists($path)) {
            $this->error("Backup already exists at: {$path}");
            return self::FAILURE;
        }

        $this->line('');
        $this->line('  <fg=cyan>'.config('app.name', 'Bridgeway Digital CMS').' - Database Backup</>');
        $this->line('  Connection : ' . $connection);
        $this->line('  Database   : ' . $config['database']);
        $this->line('  Host:Port  : ' . $config['host'] . ':' . $config['port']);
        $this->line('  Output     : ' . $path);
        $this->line('');

        $result = match ($config['driver']) {
            'mysql'  => $this->backupMysql($config, $path),
            'pgsql'  => $this->backupPgsql($config, $path),
            'sqlite' => $this->backupSqlite($config, $path),
            default  => $this->unsupported($config['driver'] ?? 'unknown'),
        };

        if ($result === self::SUCCESS) {
            $size = $this->humanSize(filesize($path));
            $this->info("  Backup saved: {$path} ({$size})");

            if ($this->option('with-storage')) {
                $this->backupStorage($connection);
            }

            $keep = (int) ($this->option('keep') ?? 7);
            if ($keep > 0) {
                $this->pruneOldBackups($keep);
            }

            $this->line('');
            $this->line('  <fg=green>Done.</> To restore:');
            $this->line('  php artisan db:restore ' . basename($path));
            $this->line('  php artisan db:restore latest');
            $this->line('');
        }

        return $result;
    }

    // ── Drivers ────────────────────────────────────────────────────────────────

    protected function backupPgsql(array $config, string $path): int
    {
        $process = new Process([
            'pg_dump',
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-acl',
            '--no-privileges',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-p', (string) ($config['port'] ?? 5432),
            '-U', (string) ($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ]);
        $process->setEnv(['PGPASSWORD' => (string) ($config['password'] ?? '')]);

        return $this->runToGzipFile($process, $path);
    }

    protected function backupMysql(array $config, string $path): int
    {
        $process = new Process([
            'mysqldump',
            '--single-transaction',
            '--routines',
            '--triggers',
            '--add-drop-table',
            '--default-character-set=utf8mb4',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? 3306),
            '-u', (string) ($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ]);
        $process->setEnv(['MYSQL_PWD' => (string) ($config['password'] ?? '')]);

        return $this->runToGzipFile($process, $path);
    }

    protected function backupSqlite(array $config, string $path): int
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || ! File::exists($database)) {
            $this->error('SQLite database file was not found.');
            return self::FAILURE;
        }

        // SQLite: copy the file then gzip it
        $tmp = $path . '.tmp';
        File::copy($database, $tmp);
        $process = new Process(['gzip', '-9', '-c', $tmp]);
        $handle  = fopen($path, 'wb');
        $process->run(fn ($t, $b) => $t === Process::OUT && fwrite($handle, $b));
        fclose($handle);
        File::delete($tmp);

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    // ── Storage archive ────────────────────────────────────────────────────────

    protected function backupStorage(string $connection): void
    {
        $src = storage_path('app/public');

        if (! is_dir($src)) {
            $this->warn('  Storage path not found, skipping: ' . $src);
            return;
        }

        $dir      = storage_path('app/backups/storage');
        $filename = $connection . '-storage-' . now()->format('Ymd-His') . '.tar.gz';
        $path     = $dir . '/' . $filename;

        File::ensureDirectoryExists($dir);

        $this->line('  Archiving storage files...');
        $process = new Process(['tar', '-czf', $path, '-C', $src, '.']);
        $process->setTimeout(300);
        $process->run();

        if ($process->isSuccessful()) {
            $size = $this->humanSize(filesize($path));
            $this->info("  Storage archive saved: {$path} ({$size})");
        } else {
            $this->warn('  Storage archive failed: ' . $process->getErrorOutput());
        }
    }

    // ── List ───────────────────────────────────────────────────────────────────

    protected function listBackups(): int
    {
        $files = $this->getBackupFiles();

        if (empty($files)) {
            $this->warn('No backups found in: ' . $this->backupDir);
            $this->line('Run: php artisan db:backup');
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
        $this->line('  Restore with: php artisan db:restore <filename>');
        $this->line('             or: php artisan db:restore latest');
        $this->line('');

        return self::SUCCESS;
    }

    // ── Prune ─────────────────────────────────────────────────────────────────

    protected function pruneOldBackups(int $keep): void
    {
        $files = $this->getBackupFiles();

        if (count($files) <= $keep) {
            return;
        }

        $toDelete = array_slice($files, $keep);

        foreach ($toDelete as $file) {
            File::delete($file);
        }

        $this->line('  Pruned ' . count($toDelete) . ' old backup(s), kept ' . $keep . '.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    protected function runToGzipFile(Process $process, string $path): int
    {
        $process->setTimeout(600);
        $handle = fopen($path, 'wb');

        if (! $handle) {
            $this->error("Cannot open output file: {$path}");
            return self::FAILURE;
        }

        $gz = gzopen($path, 'wb9');

        if (! $gz) {
            fclose($handle);
            $this->error("Cannot open gzip stream: {$path}");
            return self::FAILURE;
        }

        fclose($handle);

        $process->run(function (string $type, string $buffer) use ($gz) {
            if ($type === Process::OUT) {
                gzwrite($gz, $buffer);
            }
        });

        gzclose($gz);

        if (! $process->isSuccessful()) {
            File::delete($path);
            $this->error($process->getErrorOutput() ?: 'Backup process failed.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function pgsqlDirectConfig(array $config): array
    {
        // Use DB_DIRECT_PORT and DB_DIRECT_DATABASE when set (bypasses PgBouncer).
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

    protected function defaultPath(string $connection): string
    {
        $filename = $connection . '-' . now()->format('Ymd-His') . '.sql.gz';
        return $this->backupDir . '/' . $filename;
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
        $this->error("Driver [{$driver}] is not supported by db:backup.");
        return self::FAILURE;
    }
}
