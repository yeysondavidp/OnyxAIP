<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Daily backup: MySQL dump + app storage snapshot (US-00.7).
 *
 * Creates:  {BACKUP_PATH}/{YYYY-MM-DD}/db_{ts}.sql.gz
 *           {BACKUP_PATH}/{YYYY-MM-DD}/storage_{ts}.tar.gz
 * Prunes:   daily dirs older than BACKUP_RETAIN_DAYS.
 *
 * Scheduled by routes/console.php at 02:00 daily.
 */
class BackupApplication extends Command
{
    protected $signature = 'backup:application';

    protected $description = 'Dump the database and snapshot app storage to the backup destination (US-00.7)';

    public function handle(): int
    {
        $backupPath = rtrim((string) config('backup.path'), '/');
        $retain     = max(1, (int) config('backup.retain_days', 7));
        $timestamp  = now()->format('Ymd_His');
        $date       = now()->format('Y-m-d');
        $dest       = "{$backupPath}/{$date}";

        if (! is_dir($dest) && ! mkdir($dest, 0750, true) && ! is_dir($dest)) {
            $this->error("Cannot create backup directory: {$dest}");

            return self::FAILURE;
        }

        $dbOk      = $this->dumpDatabase($dest, $timestamp);
        $storageOk = $this->snapshotStorage($dest, $timestamp);
        $this->pruneOldBackups($backupPath, $retain);

        if ($dbOk && $storageOk) {
            $this->info("Backup complete → {$dest}");
            Log::info('backup.complete', ['destination' => $dest]);

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    private function dumpDatabase(string $dest, string $timestamp): bool
    {
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (int) config('database.connections.mysql.port', 3306);
        $db   = (string) config('database.connections.mysql.database', '');
        $user = (string) config('database.connections.mysql.username', '');
        $pass = (string) config('database.connections.mysql.password', '');
        $file = "{$dest}/db_{$timestamp}.sql.gz";

        // Password via env var — never exposed in ps output or shell history.
        $cmd = sprintf(
            'mysqldump -h %s -P %d -u %s %s | gzip > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($file),
        );

        // Pass as a string so Process::run uses fromShellCommandline; the command line
        // starts with 'bash' and Process::fake key matching (Str::is) works correctly.
        $result = Process::env(['MYSQL_PWD' => $pass])->run('bash -c '.escapeshellarg($cmd));

        if ($result->failed()) {
            $this->error('DB dump failed.');
            Log::error('backup.db_failed', ['stderr' => $result->errorOutput()]);

            return false;
        }

        $this->line("  DB      → {$file}");

        return true;
    }

    private function snapshotStorage(string $dest, string $timestamp): bool
    {
        $storagePath = storage_path('app');
        $file        = "{$dest}/storage_{$timestamp}.tar.gz";

        $result = Process::run([
            'tar', '-czf', $file,
            '-C', dirname($storagePath),
            basename($storagePath),
        ]);

        if ($result->failed()) {
            $this->error('Storage snapshot failed.');
            Log::error('backup.storage_failed', ['stderr' => $result->errorOutput()]);

            return false;
        }

        $this->line("  Storage → {$file}");

        return true;
    }

    private function pruneOldBackups(string $backupPath, int $retainDays): void
    {
        if (! is_dir($backupPath)) {
            return;
        }

        $cutoffTs = now()->subDays($retainDays)->startOfDay()->getTimestamp();

        foreach (new \DirectoryIterator($backupPath) as $item) {
            if (! $item->isDir() || $item->isDot()) {
                continue;
            }

            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $item->getFilename());
            if ($parsed === false) {
                continue;
            }

            if ($parsed->getTimestamp() < $cutoffTs) {
                $path = $item->getPathname();
                Process::run(['rm', '-rf', $path]);
                $this->line("  Pruned  → {$item->getFilename()}");
                Log::info('backup.pruned', ['path' => $path]);
            }
        }
    }
}
