<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GallonTransaction extends Model
{
    protected $fillable = ['customer_id', 'type', 'quantity', 'deposit_amount', 'note', 'transacted_at', 'created_by'];

    protected $casts = ['deposit_amount' => 'decimal:2', 'transacted_at' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
