<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Model;

class Investor extends Model
{
    use HasUserTracking;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'nid',
        'address',
        'investment_amount',
        'profit_return',
        'profit_rate',
        'investment_date',
        'close_date',
        'status',
        'notes',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function calculateProfit()
    {
        return ($this->investment_amount * $this->profit_rate) / 100;
    }
}
