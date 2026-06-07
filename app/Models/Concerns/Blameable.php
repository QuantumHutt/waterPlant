<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Automatically stamps created_by / updated_by from the authenticated user.
 * Only add this trait to models whose table has `created_by` and `updated_by`
 * nullable FK columns.
 */
trait Blameable
{
    public static function bootBlameable(): void
    {
        static::creating(function ($model): void {
            $userId = auth()->id();
            if ($userId === null) {
                return;
            }
            $model->created_by ??= $userId;
            $model->updated_by ??= $userId;
        });

        static::updating(function ($model): void {
            if (($userId = auth()->id()) !== null) {
                $model->updated_by = $userId;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
