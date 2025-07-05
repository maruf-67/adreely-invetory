<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasUserTracking;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'total_price',
        'total_received_amount',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_received_amount' => 'decimal:2',
    ];

    protected $appends = ['remaining_quantity'];

    /**
     * Get the purchase order that owns the item.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the product for this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all shipment items for this purchase order item.
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(PurchaseShipmentItem::class);
    }

    /**
     * Get remaining quantity to be received.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return $this->quantity_ordered - $this->quantity_received;
    }

    /**
     * Check if item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    /**
     * Update total received amount from shipments.
     */
    public function updateTotalReceivedAmount(): void
    {
        $total = $this->shipmentItems()->sum('total_price');
        $this->update(['total_received_amount' => $total]);
    }
}
