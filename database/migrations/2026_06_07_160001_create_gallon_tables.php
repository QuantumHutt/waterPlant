<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallon_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('type', 20); // issue|return|deposit|deposit_refund|missing|damaged
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->string('note')->nullable();
            $table->date('transacted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_gallon_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->integer('held_qty')->default(0);     // gallons currently with the customer
            $table->integer('missing_qty')->default(0);
            $table->integer('damaged_qty')->default(0);
            $table->decimal('deposit_held', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_gallon_balances');
        Schema::dropIfExists('gallon_transactions');
    }
};
