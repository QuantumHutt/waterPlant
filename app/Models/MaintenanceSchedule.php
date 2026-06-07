<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceSchedule extends Model
{
    protected $fillable = ['asset', 'title', 'frequency_days', 'last_done_at', 'next_due_at', 'status'];

    protected $casts = ['last_done_at' => 'date', 'next_due_at' => 'date'];

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }
}
