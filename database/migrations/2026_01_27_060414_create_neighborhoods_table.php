<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // En la migración de 'neighborhoods' o 'settings'
            $table->string('expense_calculation_type')->default('fixed'); // 'fixed' o 'proportional'
            $table->decimal('fixed_amount', 10, 2)->nullable(); // Para CC1 ($40.000 para todos)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};
