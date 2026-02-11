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
        Schema::table('unit_expenses', function (Blueprint $table) {
            $table->date('monthly_interest_applied_at')->nullable()->after('paid_amount');
            $table->date('extraordinary_interest_applied_at')->nullable()->after('monthly_interest_applied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_expenses', function (Blueprint $table) {
            $table->dropColumn(['monthly_interest_applied_at', 'extraordinary_interest_applied_at']);
        });
    }
};

