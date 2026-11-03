<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Price is typed in at each sale (e.g. cooler refill: 20 / 30 / …), by any POS user.
            $table->boolean('is_manual_price')->default(false)->after('is_service');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('is_manual_price'));
    }
};
