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

    public function render(): \Illuminate\Http\JsonResponse
    {
        return \App\Support\ApiResponse::error($this->getMessage(), 422);
    }
}
