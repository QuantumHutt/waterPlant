<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ProductionBatch extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;

    protected $fillable = [
        'batch_number', 'warehouse_id', 'operator_id', 'production_date', 'expiry_date', 'status',
        'raw_water_litres', 'filtered_water_litres', 'wastage_litres', 'bottles_produced',
        'production_cost', 'note', 'created_by',
    ];

    protected $casts = [
        'production_date' => 'date',
        'expiry_date' => 'date',
        'raw_water_litres' => 'decimal:2',
        'filtered_water_litres' => 'decimal:2',
        'wastage_litres' => 'decimal:2',
        'production_cost' => 'decimal:2',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(ProductionOutput::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }
}
