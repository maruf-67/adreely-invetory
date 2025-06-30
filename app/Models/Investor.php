<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investor extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'contact_info',
    ];

    /**
     * Get the business that owns the investor.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get all investments for this investor.
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }
}
