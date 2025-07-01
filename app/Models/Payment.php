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
    ];

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
