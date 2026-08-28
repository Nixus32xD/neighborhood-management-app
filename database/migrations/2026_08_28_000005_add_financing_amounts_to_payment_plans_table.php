<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->decimal('financed_debt_amount', 12, 2)->default(0)->after('original_amount');
            $table->decimal('financing_charge_amount', 12, 2)->default(0)->after('financed_debt_amount');
            $table->decimal('cancelled_debt_amount', 12, 2)->nullable()->after('cancellation_reason');
        });

        // Los planes ya existentes no tenían recargo: su total era deuda financiada.
        DB::table('payment_plans')->update(['financed_debt_amount' => DB::raw('original_amount')]);
    }

    public function down(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropColumn(['financed_debt_amount', 'financing_charge_amount', 'cancelled_debt_amount']);
        });
    }
};
