<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApiProvider;
use Database\Factories\CardApiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardApi extends Model
{
    /** @use HasFactory<CardApiFactory> */
    use HasFactory;

    protected $fillable = [
        'card_id',
        'provider',
        'base_url',
        'auth_type',
        'api_key',
        'username',
        'password',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'provider' => ApiProvider::class,
            'api_key' => 'encrypted',
            'password' => 'encrypted',
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
