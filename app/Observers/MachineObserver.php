<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Machine;
use App\Support\MachineSshKeySync;

class MachineObserver
{
    public function saved(Machine $machine): void
    {
        app(MachineSshKeySync::class)->sync($machine);
    }

    public function deleted(Machine $machine): void
    {
        app(MachineSshKeySync::class)->remove($machine);
    }
}
