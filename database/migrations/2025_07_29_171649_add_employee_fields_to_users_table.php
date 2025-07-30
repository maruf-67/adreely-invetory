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
        Schema::table('users', function (Blueprint $table) {
            $table->date('join_date')->nullable()->after('user_type'); // Employee's joining date
            $table->decimal('salary_amount', 15, 2)->nullable()->after('join_date'); // Employee's base/monthly salary amount
            
            // Add index for employee queries
            $table->index(['business_id', 'user_type', 'join_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'user_type', 'join_date']);
            $table->dropColumn(['join_date', 'salary_amount']);
        });
    }
};
