<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'business_id',
        'user_id',
        'shop_name',
        'shop_contact',
        'type',
        'status',
        'total_amount',
        'paid_amount',
        'notes',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ExchangeItem::class);
    }

    public function payments()
    {
        return $this->hasMany(ExchangePayment::class);
    }
}
