<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'purchase_order_item_id',
        'quantity_received',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Get the shipment this item belongs to.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(PurchaseShipment::class);
    }

    /**
     * Get the purchase order item this shipment item is for.
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * Get the product through the purchase order item.
     */
    public function product(): BelongsTo
    {
        return $this->purchaseOrderItem()->product();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate total price when creating/updating
        static::saving(function ($shipmentItem) {
            if ($shipmentItem->isDirty(['quantity_received', 'unit_price'])) {
                $shipmentItem->total_price = $shipmentItem->quantity_received * $shipmentItem->unit_price;
            }
        });

        // Update related records after saving
        static::saved(function ($shipmentItem) {
            // Update purchase order item quantity received
            $purchaseOrderItem = $shipmentItem->purchaseOrderItem;
            $totalReceived = $purchaseOrderItem->shipmentItems()->sum('quantity_received');
            $purchaseOrderItem->update(['quantity_received' => $totalReceived]);
            
            // Update purchase order item total received amount
            $purchaseOrderItem->updateTotalReceivedAmount();
            
            // Update shipment total amount
            $shipmentItem->shipment->calculateTotalAmount();
            
            // Update purchase order status
            $purchaseOrderItem->purchaseOrder->updateStatus();
        });

        // Update related records after deleting
        static::deleted(function ($shipmentItem) {
            // Update purchase order item quantity received
            $purchaseOrderItem = $shipmentItem->purchaseOrderItem;
            $totalReceived = $purchaseOrderItem->shipmentItems()->sum('quantity_received');
            $purchaseOrderItem->update(['quantity_received' => $totalReceived]);
            
            // Update purchase order item total received amount
            $purchaseOrderItem->updateTotalReceivedAmount();
            
            // Update shipment total amount
            $shipmentItem->shipment->calculateTotalAmount();
            
            // Update purchase order status
            $purchaseOrderItem->purchaseOrder->updateStatus();
        });
    }
}
