<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ProductType: string
{
    case Finished = 'finished';      // sellable finished goods (bottles, gallons)
    case Raw = 'raw';                // raw water, chemicals
    case Consumable = 'consumable';  // caps, labels, packaging, filters, membranes

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
