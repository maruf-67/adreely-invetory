<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasUserTracking;

    protected $fillable = [
        'business_id',
        'name',
        'short_name',
        'is_active',
    ];

    /**
     * Get the business that owns the unit.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get all products for this unit.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
