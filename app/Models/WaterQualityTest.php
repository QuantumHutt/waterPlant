<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterQualityTest extends Model
{
    protected $fillable = [
        'production_batch_id', 'tds_value', 'ph_value', 'temperature', 'passed', 'tested_by', 'tested_at', 'remarks',
    ];

    protected $casts = [
        'tds_value' => 'decimal:2', 'ph_value' => 'decimal:2', 'temperature' => 'decimal:2',
        'passed' => 'boolean', 'tested_at' => 'date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }
}
