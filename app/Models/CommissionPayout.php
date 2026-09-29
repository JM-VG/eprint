<?php

namespace App\Models;

use Database\Factories\CommissionPayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionPayout extends Model
{
    /** @use HasFactory<CommissionPayoutFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'paid_by',
        'period_start',
        'period_end',
        'amount',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'payout_id');
    }
}
