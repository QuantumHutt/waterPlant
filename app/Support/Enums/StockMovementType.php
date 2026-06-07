<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum StockMovementType: string
{
    case In = 'in';                       // manual stock in / opening
    case Out = 'out';                     // manual stock out / issue
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';       // audit / correction
    case Damage = 'damage';
    case Wastage = 'wastage';
    case ProductionIn = 'production_in';  // finished goods produced
    case ProductionOut = 'production_out'; // raw materials consumed
    case Sale = 'sale';
    case Purchase = 'purchase';
    case Return = 'return';

    /** Movements that increase on-hand quantity. */
    public function isInbound(): bool
    {
        return in_array($this, [
            self::In, self::TransferIn, self::ProductionIn, self::Purchase, self::Return,
        ], true);
    }

    /** The signed multiplier applied to the (always-positive) movement quantity. */
    public function sign(): int
    {
        return $this->isInbound() ? 1 : -1;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
