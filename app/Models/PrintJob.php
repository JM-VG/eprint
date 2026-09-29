<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleSource;
use Database\Factories\PrintJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrintJob extends Model
{
    /** @use HasFactory<PrintJobFactory> */
    use HasFactory;

    protected $fillable = [
        'claim_code',
        'user_id',
        'location_id',
        'service_id',
        'source',
        'original_name',
        'stored_path',
        'pdf_path',
        'file_type',
        'file_size',
        'page_count',
        'page_range',
        'copies',
        'duplex',
        'sets',
        'notes',
        'unit_price',
        'estimated_total',
        'final_total',
        'sheets_used',
        'status',
        'payment_method',
        'payment_status',
        'reject_reason',
        'received_by',
        'claimed_at',
        'files_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => SaleSource::class,
            'status' => JobStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'file_size' => 'integer',
            'page_count' => 'integer',
            'copies' => 'integer',
            'duplex' => 'boolean',
            'sets' => 'integer',
            'sheets_used' => 'integer',
            'unit_price' => 'decimal:2',
            'estimated_total' => 'decimal:2',
            'final_total' => 'decimal:2',
            'claimed_at' => 'datetime',
            'files_deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(JobLog::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function gcashPayment(): HasOne
    {
        return $this->hasOne(GcashPayment::class);
    }
}
