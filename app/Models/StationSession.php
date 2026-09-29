<?php

namespace App\Models;

use App\Enums\CloseReason;
use Database\Factories\StationSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationSession extends Model
{
    /** @use HasFactory<StationSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'opened_at',
        'last_seen_at',
        'closed_at',
        'close_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'closed_at' => 'datetime',
            'close_reason' => CloseReason::class,
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
}
