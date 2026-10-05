<?php

namespace App\Models;

use App\Realtime\Model;
use Database\Factories\GcashPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GcashPayment extends Model
{
    /** @use HasFactory<GcashPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'amount',
        'print_job_id',
        'sale_id',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
