<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PaymentMethod extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'business_id',
        'name',
        'type',
        'balance',
        'account_number',
        'details',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'details' => 'array',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the business that owns this payment method.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get all payments using this method.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope for filtering by business.
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope for active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for filtering by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Adjust payment method balance (without creating user balance history).
     * User balance adjustments should be handled separately in the business logic.
     * 
     * @param float $amount Amount to adjust (positive for credit, negative for debit)
     * @param string $description Description for the transaction
     * @param Model $reference Reference model for the transaction
     * @param string $referenceNumber Optional reference number
     * @return void
     */
    public function adjustBalance(float $amount, string $description, $reference = null, string $referenceNumber = null): void
    {
        $previousBalance = $this->balance;
        $newBalance = $previousBalance + $amount;
        
        // Update the payment method balance only
        $this->update(['balance' => $newBalance]);
    }

    /**
     * Check if payment method has sufficient balance for a transaction.
     * 
     * @param float $amount Amount to check
     * @return bool
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
