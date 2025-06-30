<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtraIncomeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
    ];

    /**
     * Get the business that owns the extra income type.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get all extra incomes for this type.
     */
    public function extraIncomes(): HasMany
    {
        return $this->hasMany(ExtraIncome::class);
    }
}
