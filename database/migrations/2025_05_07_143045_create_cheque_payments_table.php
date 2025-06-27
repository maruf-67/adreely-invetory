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
        Schema::create('cheque_payments', function (Blueprint $table) {
            $table->id();
            $table->enum('related_type', ['purchase', 'sales']);
            $table->unsignedBigInteger('related_id');
            $table->enum('status', ['draft', 'clear', 'hold', 'rejected']);
            $table->decimal('amount', 10, 2);
            $table->string('bank_name')->nullable();
            $table->string('cheque_number')->nullable();
            $table->foreignId('updated_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheque_payments');
    }
};
