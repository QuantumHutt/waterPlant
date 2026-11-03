<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // NULL = cost not configured yet (e.g. 19L refill). Never treated as zero cost.
            $table->decimal('cost_price', 18, 4)->nullable()->default(null)->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            // Cost per unit captured at the moment of sale, so later price changes never
            // rewrite historical profit. NULL = cost was unknown when sold (profit pending).
            $table->decimal('unit_cost', 18, 4)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('unit_cost'));

        DB::table('products')->whereNull('cost_price')->update(['cost_price' => 0]);
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 18, 4)->default(0)->nullable(false)->change();
        });
    }
};
