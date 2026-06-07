<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = ['employee_id', 'month', 'basic', 'overtime', 'advances', 'deductions', 'net', 'status'];

    protected $casts = [
        'basic' => 'decimal:2', 'overtime' => 'decimal:2', 'advances' => 'decimal:2',
        'deductions' => 'decimal:2', 'net' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
