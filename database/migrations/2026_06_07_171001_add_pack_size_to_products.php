<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Bottles per carton/"pat" (e.g. 1.5L pat = 6, 500ml pat = 24). 1 = sold singly.
            $table->unsignedInteger('pack_size')->default(1)->after('water_litres');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('pack_size'));
    }
};
