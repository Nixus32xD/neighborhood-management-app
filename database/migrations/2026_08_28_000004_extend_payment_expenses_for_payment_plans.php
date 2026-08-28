<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->foreignId('unit_expense_id')->nullable()->change();
            $table->string('payment_type')->default('period')->after('reference');
            $table->foreignId('payment_plan_id')->nullable()->after('payment_type')->constrained()->nullOnDelete();
            $table->foreignId('payment_plan_installment_id')->nullable()->after('payment_plan_id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('payment_plan_installment_id')->constrained('users')->nullOnDelete();
            $table->index(['payment_plan_id', 'payment_type']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->dropIndex(['payment_plan_id', 'payment_type']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('payment_plan_installment_id');
            $table->dropConstrainedForeignId('payment_plan_id');
            $table->dropColumn('payment_type');
        });
    }
};
