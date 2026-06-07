<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->morphs('payable');                // invoice | purchase_order …
            $table->decimal('amount', 18, 2);
            $table->string('method', 20)->default('cash'); // cash|bank|cheque|card|credit
            $table->string('direction', 10)->default('in'); // in (received) | out (paid)
            $table->date('paid_at');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
