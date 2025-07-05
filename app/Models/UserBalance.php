<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserBalance extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'business_id',
        'user_id',
        'balanceable_type',
        'balanceable_id',
        'amount',
        'balance_type',
        'previous_balance',
        'new_balance',
        'description',
        'transaction_date',
        'reference_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    /**
     * Get the owning balanceable model.
     */
    public function balanceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the business that owns this balance record.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user this balance record belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a balance record.
     */
    public static function createRecord(
        int $businessId,
        int $userId,
        string $balanceType,
        float $amount,
        string $description,
        Model $reference = null,
        string $referenceNumber = null
    ): self {
        $user = User::find($userId);
        $previousBalance = $user->current_balance;
        
        $newBalance = $balanceType === 'credit' 
            ? $previousBalance + $amount 
            : $previousBalance - $amount;

        return self::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'balanceable_type' => $reference ? get_class($reference) : null,
            'balanceable_id' => $reference ? $reference->id : null,
            'amount' => $amount,
            'balance_type' => $balanceType,
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'description' => $description,
            'transaction_date' => now()->toDateString(),
            'reference_number' => $referenceNumber,
        ]);
    }

    /**
     * Scope for filtering by business.
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope for filtering by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for filtering by balance type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('balance_type', $type);
    }

    /**
     * Scope for date range filtering.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}
