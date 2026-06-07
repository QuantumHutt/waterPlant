<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ProductUnit: string
{
    case Pcs = 'pcs';
    case Litre = 'litre';
    case Kg = 'kg';
    case Roll = 'roll';
    case Gallon = 'gallon';

    public function label(): string
    {
        return match ($this) {
            self::Pcs => 'Pieces',
            self::Litre => 'Litre',
            self::Kg => 'Kilogram',
            self::Roll => 'Roll',
            self::Gallon => 'Gallon',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
