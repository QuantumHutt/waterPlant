<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Single | pack (the saleable form). Inventory is always tracked at single level.
            $table->string('unit_type', 10)->default('single')->after('pack_size');
            // A "pack/pat" product points to the single bottle it bundles. Selling 1 pack
            // deducts pack_size units of this base product from inventory (one shared stock).
            $table->foreignId('pack_of_product_id')->nullable()->after('unit_type')
                ->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pack_of_product_id');
        });
    }
};
