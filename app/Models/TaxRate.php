<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class TaxRate extends Model implements Auditable
{
    use AuditableTrait;
    use Blameable;
    use SoftDeletes;

    protected $fillable = ['name', 'rate', 'is_inclusive', 'is_active', 'created_by', 'updated_by'];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_inclusive' => 'boolean',
        'is_active' => 'boolean',
    ];
}
