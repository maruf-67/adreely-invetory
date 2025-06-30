<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Model;

class ChequePayment extends Model
{
    use HasUserTracking;

    protected $fillable = [
        'related_type',
        'related_id',
        'status',
        'amount',
        'bank_name',
        'cheque_number',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Dynamic relation for either 'purchase_order' or 'sales_order'
    public function related()
    {
        return $this->morphTo();
    }
}
