<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SalesOrder extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'business_id',
        'customer_id',
        'order_number',
        'invoice_number',
        'order_date',
        'expected_delivery_date',
        'delivered_date',
        'status',
        'sub_total',
        'discount',
        'discount_type',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'extra_amount',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'delivered_date' => 'date',
        'sub_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'extra_amount' => 'decimal:2',
    ];

    protected $appends = ['due_amount'];

    /**
     * Get the business that owns the sales order.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the customer for this sales order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get all items for this sales order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /**
     * Get all shipments for this sales order.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(SalesShipment::class);
    }

    /**
     * Get all payments for this sales order.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    /**
     * Get due amount attribute.
     */
    public function getDueAmountAttribute(): float
    {
        // Only consider cleared payments for due amount calculation
        $clearedPayments = $this->payments()->where('status', 'clear')->sum('amount');
        return max(0, $this->total_amount - $clearedPayments);
    }

    /**
     * Generate order number.
     */
    public function generateOrderNumber(): void
    {
        if (!$this->order_number) {
            $date = $this->order_date->format('Ymd');
            $lastOrder = static::where('business_id', $this->business_id)
                ->where('order_number', 'like', "SO-{$date}-%")
                ->orderBy('order_number', 'desc')
                ->first();

            $sequence = 1;
            if ($lastOrder) {
                $lastSequence = (int) substr($lastOrder->order_number, -4);
                $sequence = $lastSequence + 1;
            }

            $this->order_number = sprintf('SO-%s-%04d', $date, $sequence);
            $this->save();
        }
    }

    /**
     * Generate invoice number.
     */
    public function generateInvoiceNumber(): void
    {
        if (!$this->invoice_number && in_array($this->status, ['partial', 'shipped', 'delivered', 'completed'])) {
            $date = now()->format('Ymd');
            $lastInvoice = static::where('business_id', $this->business_id)
                ->where('invoice_number', 'like', "INV-{$date}-%")
                ->orderBy('invoice_number', 'desc')
                ->first();

            $sequence = 1;
            if ($lastInvoice) {
                $lastSequence = (int) substr($lastInvoice->invoice_number, -4);
                $sequence = $lastSequence + 1;
            }

            $this->invoice_number = sprintf('INV-%s-%04d', $date, $sequence);
            $this->save();
        }
    }

    /**
     * Update paid amount from cleared payments only.
     */
    public function updatePaidAmount(): void
    {
        $total = $this->payments()->where('status', 'clear')->sum('amount');
        $this->update(['paid_amount' => $total]);
    }

    /**
     * Update status based on shipments and payments.
     */
    public function updateStatus(): void
    {
        $totalOrdered = $this->items()->sum('quantity_ordered');
        $totalShipped = $this->items()->sum('quantity_shipped');

        if ($totalShipped >= $totalOrdered && $this->paid_amount >= $this->total_amount) {
            $status = 'completed';
        } elseif ($totalShipped >= $totalOrdered) {
            $status = 'shipped'; // Changed from 'delivered' to 'shipped'
        } elseif ($totalShipped > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        $this->update(['status' => $status]);
    }
}
