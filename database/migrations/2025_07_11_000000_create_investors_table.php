<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('nid')->nullable(); // National ID
            $table->string('address')->nullable();
            $table->decimal('investment_amount', 15, 2);
            $table->decimal('profit_return', 15, 2)->default(0);
            $table->decimal('profit_rate', 5, 2)->default(0); // e.g. 10.00 for 10%
            $table->date('investment_date');
            $table->date('close_date')->nullable();
            $table->enum('status', ['active', 'closed', 'extended'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('investors');
    }
};
