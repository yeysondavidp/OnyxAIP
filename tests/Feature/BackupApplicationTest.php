<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Proves backup:application runs mysqldump + tar and prunes old directories (US-00.7).
 */
class BackupApplicationTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/onyx_backup_test_'.uniqid();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tmpDir)) {
            exec('rm -rf '.escapeshellarg($this->tmpDir));
        }
    }

    public function test_runs_mysqldump_and_tar(): void
    {
        Process::fake();
        config(['backup.path' => $this->tmpDir]);

        $this->artisan('backup:application')->assertExitCode(0);

        Process::assertRan(function ($process): bool {
            $cmd = is_array($process->command)
                ? implode(' ', $process->command)
                : (string) $process->command;

            return str_contains($cmd, 'mysqldump');
        });

        Process::assertRan(function ($process): bool {
            return is_array($process->command) && in_array('tar', $process->command, true);
        });
    }

    public function test_prunes_directories_older_than_retain_days(): void
    {
        Process::fake();
        mkdir($this->tmpDir, 0750, true);
        mkdir("{$this->tmpDir}/2020-01-01", 0750); // well beyond any retention window

        config(['backup.path' => $this->tmpDir, 'backup.retain_days' => 7]);

        $this->artisan('backup:application')->assertExitCode(0);

        Process::assertRan(function ($process): bool {
            return is_array($process->command)
                && in_array('rm', $process->command, true)
                && in_array('-rf', $process->command, true)
                && str_contains(implode(' ', $process->command), '2020-01-01');
        });
    }

    public function test_returns_failure_when_db_dump_fails(): void
    {
        // '*' fakes ALL processes; we only care that the command exits 1
        // when the DB dump (bash -c mysqldump ...) fails.
        Process::fake([
            'bash*' => Process::result('', 'mysqldump: error connecting', 1),
            'tar *' => Process::result('', '', 0),
        ]);
        config(['backup.path' => $this->tmpDir]);

        $this->artisan('backup:application')->assertExitCode(1);
    }
}
