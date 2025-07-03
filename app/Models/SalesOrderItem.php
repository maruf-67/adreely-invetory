<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_shipped',
        'quantity_delivered',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_shipped' => 'integer',
        'quantity_delivered' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected $appends = ['remaining_quantity'];

    /**
     * Get the sales order that owns the item.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get the product for this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all shipment items for this sales order item.
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(SalesShipmentItem::class);
    }

    /**
     * Get remaining quantity to be shipped.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return $this->quantity_ordered - $this->quantity_shipped;
    }

    /**
     * Check if item is fully shipped.
     */
    public function isFullyShipped(): bool
    {
        return $this->quantity_shipped >= $this->quantity_ordered;
    }

    /**
     * Check if item is fully delivered.
     */
    public function isFullyDelivered(): bool
    {
        return $this->quantity_delivered >= $this->quantity_ordered;
    }
}
