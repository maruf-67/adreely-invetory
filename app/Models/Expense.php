<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasUserTracking;

    protected $fillable = [
        'business_id',
        'expense_category_id',
        'user_id',
        'title',
        'description',
        'amount',
        'expense_date',
        'reference_number',
        'receipt_file',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    /**
     * Get the business that owns the expense.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the expense category for this expense.
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * Get the user who created this expense.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
