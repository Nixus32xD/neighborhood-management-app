<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_expense_id')->constrained()->restrictOnDelete();
            $table->decimal('financed_amount', 12, 2);
            $table->decimal('settled_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['payment_plan_id', 'unit_expense_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_items');
    }
};
