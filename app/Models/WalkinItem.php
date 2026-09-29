<?php

namespace App\Models;

use Database\Factories\WalkinItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkinItem extends Model
{
    /** @use HasFactory<WalkinItemFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'service_id',
        'quantity',
        'sheets_used',
        'unit_price',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sheets_used' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
