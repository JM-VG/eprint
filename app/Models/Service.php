<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'paper_size',
        'color_mode',
        'unit',
        'pieces_per_set',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pieces_per_set' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function walkinItems(): HasMany
    {
        return $this->hasMany(WalkinItem::class);
    }
}
