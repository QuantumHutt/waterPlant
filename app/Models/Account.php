<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'parent_id', 'code', 'name', 'type', 'sub_type', 'is_group', 'opening_balance', 'is_active',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'is_group' => 'boolean',
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public static function byCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
