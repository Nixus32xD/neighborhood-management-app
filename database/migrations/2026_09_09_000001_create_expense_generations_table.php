<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('neighborhood_id')->constrained()->cascadeOnDelete();
            $table->string('period');
            $table->decimal('base_amount', 12, 2);
            $table->decimal('base_meters', 10, 2);
            $table->timestamps();

            $table->unique(['neighborhood_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_generations');
    }
};
