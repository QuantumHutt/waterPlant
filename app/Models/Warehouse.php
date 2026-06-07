<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Warehouse extends Model implements Auditable
{
    use AuditableTrait;
    use Blameable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'location', 'is_default', 'is_active', 'created_by', 'updated_by'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::query()->first();
    }
}
