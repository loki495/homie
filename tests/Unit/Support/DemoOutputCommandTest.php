<?php

declare(strict_types=1);

use App\Support\DemoOutputCommand;

it('allows exactly the commands the sandbox allows', function (string $command) {
    expect(DemoOutputCommand::isAllowed($command))->toBeTrue();
})->with(['uptime', 'df -h', 'date', 'whoami', 'echo hello world']);

it('rejects anything else, including chaining and a trailing newline', function (string $command) {
    expect(DemoOutputCommand::isAllowed($command))->toBeFalse();
})->with([
    'cat .env',
    'id; cat .env',
    'uptime && id',
    'uptime | sh',
    "uptime\nid",
    'echo $(id)',
    'echo `id`',
    'ssh -p 22 evil@host id',
    '',
    'UPTIME',
    'echo',
]);

it('wraps the command as one quoted ssh argument to the sandbox', function () {
    config([
        'homie.ssh_key_path' => '/keys',
        'homie.demo_sandbox_host' => 'output-sandbox',
        'homie.demo_sandbox_port' => 2222,
        'homie.demo_sandbox_ssh_user' => 'sandbox',
    ]);

    $ssh = DemoOutputCommand::toSshCommand('echo hi');

    expect($ssh)->toStartWith('ssh -o BatchMode=yes')
        ->and($ssh)->toContain("-i '/keys/demo-sandbox'")
        ->and($ssh)->toContain('-p 2222')
        ->and($ssh)->toEndWith("'sandbox@output-sandbox' 'echo hi'");
});
