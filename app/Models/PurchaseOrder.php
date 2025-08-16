<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseOrder extends Model
{
    use HasUserTracking;

    protected $fillable = [
        'business_id',
        'supplier_id',
        'order_number',
        'order_date',
        'expected_delivery_date',
        'status',
        'sub_total',
        'discount',
        'total_amount',
        'paid_amount',
        'received_amount',
        'extra_amount',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'sub_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'extra_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    protected $appends = ['due_amount'];

    public function getDueAmountAttribute()
    {
        // Only consider cleared payments for due amount calculation
        $clearedPayments = $this->payments()->where('status', Payment::STATUS_CLEAR)->sum('amount');
        return max(0, $this->total_amount - $clearedPayments);
    }

    /**
     * Get the business that owns the purchase order.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the supplier for this purchase order.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    /**
     * Get the user who created this purchase order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all items for this purchase order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get all shipments for this purchase order.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(PurchaseShipment::class);
    }

    /**
     * Get all payments for this purchase order.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    /**
     * Update purchase order status based on received quantities.
     */
    public function updateStatus(): void
    {
        $totalOrdered = $this->items->sum('quantity_ordered');
        $totalReceived = $this->items->sum('quantity_received');

        if ($totalReceived == 0) {
            $status = 'pending';
        } elseif ($totalReceived >= $totalOrdered) {
            $status = 'completed';
        } else {
            $status = 'partial';
        }

        $this->update(['status' => $status]);
    }

    /**
     * Generate order number if not provided.
     */
    public function generateOrderNumber(): string
    {
        if (!$this->order_number) {
            $date = $this->order_date->format('Ymd');
            $count = self::where('business_id', $this->business_id)
                        ->whereDate('order_date', $this->order_date)
                        ->count();
            $this->order_number = "PO-{$date}-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $this->save();
        }
        return $this->order_number;
    }

    /**
     * Update paid amount from payments.
     */
    public function updatePaidAmount(): void
    {
        $totalPaid = $this->payments()->where('status', 'clear')->sum('amount');
        $this->update(['paid_amount' => $totalPaid]);
        
        // Calculate and update extra_amount but don't handle supplier balance here
        // Supplier balance should be handled explicitly in payment controllers
        $extraAmount = max(0, $totalPaid - $this->total_amount);
        $this->update(['extra_amount' => $extraAmount]);
    }

    /**
     * Handle overpayment by updating extra_amount and supplier balance.
     */
    public function handleOverpayment(): void
    {
        $previousExtraAmount = $this->getOriginal('extra_amount') ?? 0;
        $extraAmount = max(0, $this->paid_amount - $this->total_amount);
        $extraAmountDifference = $extraAmount - $previousExtraAmount;
        
        if ($extraAmountDifference != 0) {
            // Update extra_amount in purchase order
            $this->update(['extra_amount' => $extraAmount]);
            
            // Update supplier balance only if there's a difference
            if ($extraAmountDifference > 0) {
                $supplier = $this->supplier;
                $supplier->increment('current_balance', $extraAmountDifference);
            }
        }
    }

    /**
     * Update received amount from shipments.
     */
    public function updateReceivedAmount(): void
    {
        $totalReceived = $this->shipments()->sum('total_amount');
        $this->update(['received_amount' => $totalReceived]);
    }

    /**
     * Scope for filtering by business.
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
