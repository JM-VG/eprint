<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleSource;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'print_job_id',
        'location_id',
        'source',
        'payment_method',
        'amount',
        'commission_rate',
        'commission_amount',
        'payout_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => SaleSource::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'payout_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WalkinItem::class);
    }

    public function gcashPayment(): HasOne
    {
        return $this->hasOne(GcashPayment::class);
    }
}
