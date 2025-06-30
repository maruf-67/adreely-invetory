<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'employee_id',
        'month',
        'amount',
        'type',
        'created_by',
    ];

    protected $casts = [
        'month' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the business that owns the salary.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the employee for this salary.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the user who created this salary record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
