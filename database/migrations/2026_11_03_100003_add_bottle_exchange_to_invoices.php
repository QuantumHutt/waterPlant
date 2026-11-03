<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 19L exchange on a refill sale: filled company bottles handed over / empties taken back.
            // NULL = not tracked (e.g. sales without a 19L refill).
            $table->unsignedInteger('bottles_delivered')->nullable()->after('deposit_total');
            $table->unsignedInteger('bottles_returned')->nullable()->after('bottles_delivered');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['bottles_delivered', 'bottles_returned']));
    }
};
