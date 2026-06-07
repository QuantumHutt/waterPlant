<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    protected $casts = ['value' => 'array'];

    private const CACHE_KEY = 'app.settings';

    /** All settings as group => [key => value]. Cached. */
    public static function allGrouped(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return static::query()->get()->groupBy('group')
                ->map(fn ($items) => $items->pluck('value', 'key')->map(fn ($v) => $v['value'] ?? $v)->all())
                ->all();
        });
    }

    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('group', $group)->where('key', $key)->first();

        if (! $row) {
            return $default;
        }

        return $row->value['value'] ?? $default;
    }

    public static function set(string $group, string $key, mixed $value, string $type = 'string'): self
    {
        $setting = static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => ['value' => $value], 'type' => $type],
        );

        Cache::forget(self::CACHE_KEY);

        return $setting;
    }
}
