<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Overtime extends Model
{
    protected $fillable = ['employee_id', 'date', 'hours', 'rate', 'amount'];

    protected $casts = ['date' => 'date', 'hours' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
