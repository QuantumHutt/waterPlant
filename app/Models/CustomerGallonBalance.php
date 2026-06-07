<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGallonBalance extends Model
{
    protected $fillable = ['customer_id', 'held_qty', 'missing_qty', 'damaged_qty', 'deposit_held'];

    protected $casts = ['deposit_held' => 'decimal:2'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
