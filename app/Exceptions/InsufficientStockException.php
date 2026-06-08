<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function for(string $product, float $available, float $requested): self
    {
        return new self("Insufficient stock for {$product}: available {$available}, requested {$requested}.");
    }

    public function render(\Illuminate\Http\Request $request)
    {
        if ($request->expectsJson()) {
            return \App\Support\ApiResponse::error($this->getMessage(), 422);
        }

        // Web / Inertia: bounce back with an error flash so the toast shows.
        return back()->with('error', $this->getMessage());
    }
}
