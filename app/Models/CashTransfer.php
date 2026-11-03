<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money moved between the cash drawer and the bank (deposit / withdrawal). */
class CashTransfer extends Model
{
    protected $fillable = ['reference', 'from_account', 'to_account', 'amount', 'transfer_date', 'note', 'created_by'];

    protected $casts = ['amount' => 'decimal:2', 'transfer_date' => 'date'];
}
