<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    protected $fillable = [
        'reference', 'account_id', 'amount', 'received_via', 'source', 'received_at', 'note', 'created_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'received_at' => 'date'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
