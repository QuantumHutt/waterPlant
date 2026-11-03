<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Blameable;
use App\Support\Enums\ProductType;
use App\Support\Enums\ProductUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model implements Auditable
{
    use AuditableTrait;
    use Blameable;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'name', 'description', 'type', 'is_service', 'is_manual_price', 'unit', 'water_litres', 'pack_size', 'unit_type', 'pack_of_product_id',
        'cost_price', 'sale_price', 'wholesale_price', 'reorder_level', 'opening_stock',
        'track_inventory', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'type' => ProductType::class,
        'unit' => ProductUnit::class,
        'is_service' => 'boolean',
        'is_manual_price' => 'boolean',
        'water_litres' => 'decimal:3',
        'pack_size' => 'integer',
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'reorder_level' => 'decimal:3',
        'opening_stock' => 'decimal:3',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** The single bottle this pack bundles (null for normal products). */
    public function packOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pack_of_product_id');
    }

    public function isPack(): bool
    {
        return $this->pack_of_product_id !== null;
    }

    /** List price for an invoice type: shop customers (wholesale) → market price, else shop price. */
    public function priceFor(string $invoiceType): float
    {
        if ($invoiceType === 'wholesale' && $this->wholesale_price !== null) {
            return (float) $this->wholesale_price;
        }

        return (float) $this->sale_price;
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'cost_price', 'sale_price', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('product');
    }
}
