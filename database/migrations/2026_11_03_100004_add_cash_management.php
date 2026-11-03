<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money moved between the cash drawer and the bank (e.g. deposit 800 of a 1000 sale).
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('from_account', 10);   // cash | bank
            $table->string('to_account', 10);     // cash | bank
            $table->decimal('amount', 18, 2);
            $table->date('transfer_date');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // End-of-day drawer count vs what the system expects.
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->unique();
            $table->decimal('expected_cash', 18, 2);
            $table->decimal('counted_cash', 18, 2);
            $table->decimal('difference', 18, 2);   // counted − expected (minus = short)
            $table->decimal('bank_balance', 18, 2);
            $table->string('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Partial returns: how much of each line / invoice came back.
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('returned_qty', 18, 3)->default(0)->after('quantity');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('returned_total', 18, 2)->default(0)->after('grand_total');
        });
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->foreignId('invoice_item_id')->nullable()->after('sales_return_id')
                ->constrained('invoice_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_return_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('invoice_item_id'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('returned_total'));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('returned_qty'));
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('cash_transfers');
    }
};
