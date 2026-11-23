<?php

namespace App\Scanners;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Starts a scanner as a restricted child process.
 *
 * - The command is always an argument array, so nothing a user typed is ever read by a shell.
 * - The environment is replaced, not inherited: the child sees no app secrets (APP_KEY, database
 *   password, API keys).
 * - Wall-clock timeout always. CPU-time, process-count and file-size limits where `prlimit`
 *   exists (Linux). Memory is capped by the scanner's own option where it has one.
 * - If the app runs as root, the child is started as an unprivileged user with no way to regain
 *   privileges, where `setpriv` exists (Linux).
 *
 * This limits a scanner bug; it is not a full sandbox (no network or filesystem isolation).
 * See SECURITY.md.
 */
class Sandbox
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    public function run(array $command, string $cwd, string $home, array $env = []): ProcessResult
    {
        $c = config('codesentinel.scanners');
        $finder = new ExecutableFinder;
        $prefix = [];

        if ($this->shouldDropPrivileges() && ($setpriv = $finder->find('setpriv'))) {
            array_push($prefix, $setpriv, '--reuid='.$c['drop_to_uid'], '--regid='.$c['drop_to_gid'], '--clear-groups', '--no-new-privs');
        }
        if ($prlimit = $finder->find('prlimit')) {
            // No address-space limit here: Semgrep's engine and Go programs reserve far more virtual
            // memory than they use and are killed by one. Semgrep gets --max-memory instead.
            array_push($prefix, $prlimit, '--cpu='.$c['cpu_seconds'], '--nproc=256', '--fsize='.(64 * 1024 * 1024), '--core=0', '--');
        }

        return Process::path($cwd)
            ->timeout($c['timeout_seconds'])
            ->env(array_merge(self::blankInheritedEnv(), [
                'PATH' => '/usr/local/bin:/usr/bin:/bin',
                'HOME' => $home,
                'XDG_CACHE_HOME' => $home.'/.cache',
                'XDG_CONFIG_HOME' => $home.'/.config',
                'LANG' => 'C.UTF-8',
            ], $env))
            ->run(array_merge($prefix, $command));
    }

    public function shouldDropPrivileges(): bool
    {
        return function_exists('posix_geteuid') && posix_geteuid() === 0;
    }

    /** Symfony Process merges the parent environment in; `false` removes a variable for the child. */
    private static function blankInheritedEnv(): array
    {
        return array_fill_keys(array_keys(getenv() + $_ENV + array_filter($_SERVER, 'is_string')), false);
    }
}
