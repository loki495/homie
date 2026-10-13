<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Demo mode only: output cards store a bare remote command that is checked
 * against the same allowlist the SSH sandbox enforces (docker/ssh-sandbox/
 * allowed-command.sh) and is only ever run over SSH on that sandbox, never by
 * the app container's own shell. Keep the pattern in step with that script.
 */
final class DemoOutputCommand
{
    public const string SANDBOX_MACHINE_NAME = 'Demo sandbox';

    private const string ALLOWED = '/\A(?:uptime|df -h|date|whoami|echo [A-Za-z0-9 _.,:@-]{0,100})\z/';

    public static function isAllowed(string $command): bool
    {
        return preg_match(self::ALLOWED, $command) === 1;
    }

    public static function toSshCommand(string $remoteCommand): string
    {
        $keyPath = rtrim((string) config('homie.ssh_key_path'), '/').'/'.Str::slug(self::SANDBOX_MACHINE_NAME);

        return sprintf(
            'ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR -o ConnectTimeout=5 -o IdentitiesOnly=yes -i %s -p %d %s %s',
            escapeshellarg($keyPath),
            (int) config('homie.demo_sandbox_port'),
            escapeshellarg(config('homie.demo_sandbox_ssh_user').'@'.config('homie.demo_sandbox_host')),
            escapeshellarg($remoteCommand),
        );
    }
}
