<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'reference', 'payable_type', 'payable_id', 'amount', 'method', 'direction', 'paid_at', 'note', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
