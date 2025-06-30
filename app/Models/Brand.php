<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasUserTracking;

    protected $fillable = [
        'business_id',
        'name',
        'is_active',
    ];

    /**
     * Get the business that owns the brand.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get all products for this brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
