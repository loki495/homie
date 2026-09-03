<?php

declare(strict_types=1);

use App\Enums\DiscoveryMethod;
use App\Models\Machine;

it('casts discovery_method to a DiscoveryMethod enum and ssh_port to an integer', function () {
    $machine = Machine::factory()->ssh()->create(['ssh_port' => '2222']);

    expect($machine->fresh())
        ->discovery_method->toBe(DiscoveryMethod::Ssh)
        ->ssh_port->toBeInt();
});
