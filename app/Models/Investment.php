<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'investor_id',
        'amount',
        'investment_date',
        'status',
    ];

    protected $casts = [
        'investment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the business that owns the investment.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the investor for this investment.
     */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
