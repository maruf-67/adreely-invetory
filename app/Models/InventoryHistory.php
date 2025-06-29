<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryHistory extends Model
{
    

    protected $fillable = [
        'business_id',
        'product_id',
        'user_id',
        'type',
        'quantity_change',
        'reason',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
    ];

    /**
     * Get the business that owns the inventory history.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the product for this inventory history.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user responsible for this change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
