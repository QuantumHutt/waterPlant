<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_consumptions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);   // cost value of what was taken
            $table->decimal('amount_paid', 18, 2)->default(0);  // owner may pay at cost (or 0 = free)
            $table->boolean('is_paid')->default(false);
            $table->string('reason')->nullable();               // e.g. "owner home use"
            $table->foreignId('consumed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('consumed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_consumptions');
    }
};
