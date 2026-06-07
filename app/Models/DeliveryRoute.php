<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRoute extends Model
{
    protected $fillable = ['name', 'area', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
