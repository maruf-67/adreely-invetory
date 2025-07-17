<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangePayment extends Model
{
    use HasFactory, HasUserTracking;

    protected $fillable = [
        'exchange_id',
        'payment_method_id',
        'amount',
        'payment_date',
        'notes',
    ];

    public function exchange()
    {
        return $this->belongsTo(Exchange::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}