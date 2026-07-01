<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardType;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected $fillable = [
        'group_id',
        'name',
        'type',
        'icon',
        'color',
        'url',
        'sort_order',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'type' => CardType::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return HasOne<CardOutput, $this>
     */
    public function output(): HasOne
    {
        return $this->hasOne(CardOutput::class);
    }

    /**
     * @return HasOne<CardApi, $this>
     */
    public function api(): HasOne
    {
        return $this->hasOne(CardApi::class);
    }
}
