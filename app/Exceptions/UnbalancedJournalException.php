<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UnbalancedJournalException extends RuntimeException
{
    public static function make(float $debit, float $credit): self
    {
        $diff = number_format(abs($debit - $credit), 2);

        return new self("Journal entry is not balanced: debits ".number_format($debit, 2)
            ." ≠ credits ".number_format($credit, 2)." (difference Rs {$diff}).");
    }

    public function render(\Illuminate\Http\Request $request)
    {
        if ($request->expectsJson()) {
            return \App\Support\ApiResponse::error($this->getMessage(), 422);
        }

        return back()->with('error', $this->getMessage());
    }
}
