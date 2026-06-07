<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('type', 30);                       // StockMovementType
            $table->decimal('quantity', 18, 3);               // signed: + inbound, - outbound
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('balance_after', 18, 3)->default(0); // running on-hand snapshot
            $table->nullableMorphs('reference');              // source doc (transfer, adjustment, invoice…)
            $table->string('note')->nullable();
            $table->timestamp('moved_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'variant_id', 'warehouse_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
