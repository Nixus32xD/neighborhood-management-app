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
        Schema::table('owners', function (Blueprint $table) {
            $table->string('preferred_method')->nullable()->after('people_count');
            $table->string('bank_name')->nullable()->after('preferred_method');
            $table->string('account_holder')->nullable()->after('bank_name');
            $table->string('cbu', 30)->nullable()->after('account_holder');
            $table->string('alias', 50)->nullable()->after('cbu');
            $table->string('custom_method')->nullable()->after('alias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_method',
                'bank_name',
                'account_holder',
                'cbu',
                'alias',
                'custom_method',
            ]);
        });
    }
};

