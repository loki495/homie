<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CardOutputFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardOutput extends Model
{
    /** @use HasFactory<CardOutputFactory> */
    use HasFactory;

    protected $fillable = [
        'card_id',
        'command',
        'last_output',
        'last_exit_code',
        'last_run_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'last_exit_code' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
