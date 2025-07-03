<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesShipment extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'business_id',
        'sales_order_id',
        'shipment_number',
        'shipped_date',
        'expected_delivery_date',
        'delivered_date',
        'status',
        'total_amount',
        'tracking_number',
        'notes',
    ];

    protected $casts = [
        'shipped_date' => 'date',
        'expected_delivery_date' => 'date',
        'delivered_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the business that owns the shipment.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the sales order for this shipment.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get all items for this shipment.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesShipmentItem::class, 'shipment_id');
    }
}
