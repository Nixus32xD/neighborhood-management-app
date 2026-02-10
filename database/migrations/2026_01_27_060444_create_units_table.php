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
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('neighborhood_id')
                ->constrained()
                ->cascadeOnDelete();

            // Identificación
            $table->string('uf_number');

            // Dimensiones
            $table->decimal('surface_area', 10, 2)->nullable(); // m²
            $table->decimal('front', 8, 2)->nullable();         // metros
            $table->decimal('depth', 8, 2)->nullable();         // metros

            // Expensas
            $table->decimal('expense_coefficient', 8, 5)->nullable(); // % o coeficiente
            $table->decimal('base_expense', 12, 2)->nullable();       // si aplica fijo
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['neighborhood_id', 'uf_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
