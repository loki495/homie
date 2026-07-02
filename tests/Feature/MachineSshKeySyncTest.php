<?php

declare(strict_types=1);

use App\Models\Machine;
use Illuminate\Support\Str;

function sshKeyPath(Machine $machine): string
{
    return rtrim((string) config('homie.ssh_key_path'), '/').'/'.Str::slug($machine->name);
}

afterEach(function () {
    foreach (glob(rtrim((string) config('homie.ssh_key_path'), '/').'/*') ?: [] as $file) {
        unlink($file);
    }
});

it('writes the machine ssh key to storage/ssh when saved', function () {
    $machine = Machine::factory()->create(['name' => 'Test Machine Sync', 'ssh_private_key' => 'fake-key-contents']);

    $path = sshKeyPath($machine);

    expect(file_exists($path))->toBeTrue()
        ->and(trim((string) file_get_contents($path)))->toBe('fake-key-contents')
        ->and(substr(sprintf('%o', fileperms($path)), -4))->toBe('0600');
});

it('updates the synced key file when the machine key changes', function () {
    $machine = Machine::factory()->create(['name' => 'Test Machine Update', 'ssh_private_key' => 'old-key']);

    $machine->update(['ssh_private_key' => 'new-key']);

    expect(trim((string) file_get_contents(sshKeyPath($machine))))->toBe('new-key');
});

it('removes the synced key file when the ssh key is cleared', function () {
    $machine = Machine::factory()->create(['name' => 'Test Machine Clear', 'ssh_private_key' => 'fake-key-contents']);
    $path = sshKeyPath($machine);
    expect(file_exists($path))->toBeTrue();

    $machine->update(['ssh_private_key' => null]);

    expect(file_exists($path))->toBeFalse();
});

it('removes the synced key file when the machine is deleted', function () {
    $machine = Machine::factory()->create(['name' => 'Test Machine Delete', 'ssh_private_key' => 'fake-key-contents']);
    $path = sshKeyPath($machine);
    expect(file_exists($path))->toBeTrue();

    $machine->delete();

    expect(file_exists($path))->toBeFalse();
});

it('does not create a key file for machines with no ssh key', function () {
    $machine = Machine::factory()->create(['name' => 'Test Machine None', 'ssh_private_key' => null]);

    expect(file_exists(sshKeyPath($machine)))->toBeFalse();
});
