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
        Schema::create('purchase_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('purchase_shipments')->onDelete('cascade');
            $table->foreignId('purchase_order_item_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_received');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure no duplicate entries for same item in same shipment
            $table->unique(['shipment_id', 'purchase_order_item_id'], 'unique_shipment_item');
            
            // Add indexes for better performance
            $table->index(['shipment_id']);
            $table->index(['purchase_order_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_shipment_items');
    }
};
