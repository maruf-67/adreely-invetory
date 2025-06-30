<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraIncome extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'income_type_id',
        'amount',
        'date',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the business that owns the extra income.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the income type for this extra income.
     */
    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(ExtraIncomeType::class, 'income_type_id');
    }
}
