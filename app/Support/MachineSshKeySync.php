<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Machine;
use Illuminate\Support\Str;

class MachineSshKeySync
{
    public function sync(Machine $machine): void
    {
        if (! $machine->ssh_private_key) {
            $this->remove($machine);

            return;
        }

        $directory = (string) config('homie.ssh_key_path');

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($this->path($machine), rtrim($machine->ssh_private_key).PHP_EOL);
        chmod($this->path($machine), 0600);
    }

    public function remove(Machine $machine): void
    {
        $path = $this->path($machine);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function path(Machine $machine): string
    {
        $slug = Str::slug($machine->name);

        return rtrim((string) config('homie.ssh_key_path'), '/').'/'.($slug !== '' ? $slug : 'machine-'.$machine->id);
    }
}
