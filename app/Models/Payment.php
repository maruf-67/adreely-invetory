<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'business_id',
        'paymentable_type',
        'paymentable_id',
        'payment_method_id',
        'amount',
        'transaction_date',
        'details',
        'reference_number',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'status_updated_at' => 'datetime',
    ];

    // Payment status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CLEAR = 'clear';
    const STATUS_HOLD = 'hold';
    const STATUS_REJECTED = 'rejected';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get available payment statuses.
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CLEAR => 'Clear',
            self::STATUS_HOLD => 'Hold',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_BOUNCED => 'Bounced',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Check if payment is cleared.
     */
    public function isCleared(): bool
    {
        return $this->status == self::STATUS_CLEAR;
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    /**
     * Check if payment is bounced.
     */
    public function isBounced(): bool
    {
        return $this->status == self::STATUS_BOUNCED;
    }

    /**
     * Check if payment is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status == self::STATUS_CANCELLED;
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return self::getAvailableStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Determine default status based on payment method.
     */
    public function getDefaultStatusForPaymentMethod(): string
    {
        $paymentMethodType = $this->paymentMethod->type ?? 'cash';
        
        // Cheque payments default to pending
        if (in_array($paymentMethodType, ['cheque', 'check'])) {
            return self::STATUS_PENDING;
        }
        
        // Bank transfers might be pending initially
        if ($paymentMethodType == 'bank_transfer') {
            return self::STATUS_PENDING;
        }
        
        // Cash and other instant methods are clear immediately
        return self::STATUS_CLEAR;
    }

    /**
     * Update payment status and handle balance changes.
     */
    public function updateStatus(string $newStatus, string $reason = null): void
    {
        $oldStatus = $this->status;
        
        if ($oldStatus == $newStatus) {
            return; // No change needed
        }

        // Update payment status
        $this->update([
            'status' => $newStatus,
            'details' => $reason ? $this->details . "\nStatus updated: " . $reason : $this->details
        ]);

        // Handle balance and order updates based on status change
        $this->handleStatusChange($oldStatus, $newStatus);
    }

    /**
     * Handle balance and order updates when status changes.
     */
    private function handleStatusChange(string $oldStatus, string $newStatus): void
    {
        // Only process if status change involves cleared payments
        if ($oldStatus == self::STATUS_CLEAR || $newStatus == self::STATUS_CLEAR) {
            
            // Delegate to the paymentable model to handle its own updates
            if ($this->paymentable && method_exists($this->paymentable, 'handlePaymentStatusChange')) {
                $this->paymentable->handlePaymentStatusChange($this, $oldStatus, $newStatus);
            }
        }
    }

    /**
     * Get the owning paymentable model.
     */
    public function paymentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the business that owns this payment.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the payment method.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
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

    /**
     * Scope for filtering by payment type.
     */
    public function scopeForPaymentable($query, $type, $id = null)
    {
        $query->where('paymentable_type', $type);
        
        if ($id) {
            $query->where('paymentable_id', $id);
        }
        
        return $query;
    }
}
