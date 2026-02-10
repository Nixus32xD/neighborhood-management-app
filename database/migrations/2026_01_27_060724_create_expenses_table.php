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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('neighborhood_id')->constrained()->cascadeOnDelete();
            $table->string('period'); // 2026-01
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['neighborhood_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
