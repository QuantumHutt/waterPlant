<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Expense extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'reference', 'category_id', 'amount', 'paid_via', 'vendor', 'spent_at', 'note', 'created_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'spent_at' => 'date'];

    /** Who entered it (salesman / owner). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }
}
