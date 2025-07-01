<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseShipment extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'business_id',
        'purchase_order_id',
        'shipment_number',
        'received_date',
        'notes',
        'total_amount',
    ];

    protected $casts = [
        'received_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the business that owns this shipment.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the purchase order this shipment belongs to.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get all items in this shipment.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseShipmentItem::class, 'shipment_id');
    }

    /**
     * Get the user who created this shipment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate and update the total amount of this shipment.
     */
    public function calculateTotalAmount(): void
    {
        $total = $this->items()->sum('total_price');
        $this->update(['total_amount' => $total]);
    }

    /**
     * Get shipment number with auto-generation if needed.
     */
    public function getShipmentNumberAttribute($value): string
    {
        if (!$value) {
            $count = $this->purchaseOrder->shipments()->count();
            return 'SH-' . $this->purchase_order_id . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
        }
        return $value;
    }

    /**
     * Scope to filter by business.
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('received_date', [$startDate, $endDate]);
    }
}
