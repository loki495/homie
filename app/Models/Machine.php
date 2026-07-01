<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscoveryMethod;
use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'description',
        'discovery_method',
        'ssh_user',
        'ssh_port',
        'ssh_private_key',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'discovery_method' => DiscoveryMethod::class,
            'ssh_port' => 'integer',
            'ssh_private_key' => 'encrypted',
        ];
    }
}
