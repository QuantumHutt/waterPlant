<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** End-of-day drawer count against the expected cash. */
class CashClosing extends Model
{
    protected $fillable = ['closing_date', 'expected_cash', 'counted_cash', 'difference', 'bank_balance', 'note', 'closed_by'];

    protected $casts = [
        'closing_date' => 'date',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'bank_balance' => 'decimal:2',
    ];

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
