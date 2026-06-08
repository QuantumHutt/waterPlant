<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalConsumption extends Model
{
    protected $fillable = [
        'reference', 'warehouse_id', 'product_id', 'variant_id', 'quantity',
        'unit_cost', 'total_cost', 'amount_paid', 'is_paid', 'reason', 'consumed_by', 'consumed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'is_paid' => 'boolean',
        'consumed_at' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }
}
