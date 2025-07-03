<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'sales_order_item_id',
        'quantity_shipped',
        'quantity_delivered',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'quantity_shipped' => 'integer',
        'quantity_delivered' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Get the shipment that owns the item.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(SalesShipment::class);
    }

    /**
     * Get the sales order item for this shipment item.
     */
    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }
}
