<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // A service product (e.g. 19L Refill) holds no stock of its own.
            $table->boolean('is_service')->default(false)->after('type');
            // Litres of bulk filtered water consumed per unit sold (refill = 19).
            $table->decimal('water_litres', 10, 3)->default(0)->after('unit');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Refundable bottle security deposit collected on this invoice (a liability, not revenue).
            $table->decimal('deposit_total', 18, 2)->default(0)->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['is_service', 'water_litres']));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('deposit_total'));
    }
};
