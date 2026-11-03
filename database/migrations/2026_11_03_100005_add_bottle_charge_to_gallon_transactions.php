<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallon_transactions', function (Blueprint $table) {
            // Lost / damaged bottle charged to the customer (that bill). NULL = written off as a loss.
            $table->foreignId('charge_invoice_id')->nullable()->after('deposit_amount')
                ->constrained('invoices')->nullOnDelete();
            // Value lost when a bottle is written off (bottle cost at that time).
            $table->decimal('loss_value', 18, 2)->default(0)->after('charge_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('gallon_transactions', function (Blueprint $t) {
            $t->dropColumn('loss_value');
            $t->dropConstrainedForeignId('charge_invoice_id');
        });
    }
};
