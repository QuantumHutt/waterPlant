<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvance extends Model
{
    protected $fillable = ['employee_id', 'amount', 'taken_at', 'settled', 'note'];

    protected $casts = ['amount' => 'decimal:2', 'taken_at' => 'date', 'settled' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
