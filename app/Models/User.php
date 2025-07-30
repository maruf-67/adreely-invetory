<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'image',
        'address',
        'previous_due',
        'previous_credit',
        'current_balance',
        'user_type',
        'party_type',
        'join_date',        // Employee joining date
        'salary_amount',    // Employee base salary amount
        'password',
        'created_by',
        'email_verified_at', // keep for future reference
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'previous_due' => 'decimal:2',
            'previous_credit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'join_date' => 'date',
            'salary_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the business that owns the user.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user who created this user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all users created by this user.
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    /**
     * Get all businesses owned by this user.
     */
    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    /**
     * Get all purchase orders as supplier.
     */
    public function supplierPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    /**
     * Get all balance records for this user.
     */
    public function balanceRecords(): HasMany
    {
        return $this->hasMany(UserBalance::class);
    }

    /**
     * Get all sales orders as customer.
     */
    // public function customerSalesOrders(): HasMany
    // {
    //     return $this->hasMany(SalesOrder::class, 'customer_id');
    // }

    /**
     * Get all purchase orders created by this user.
     */
    public function createdPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    /**
     * Get all sales orders created by this user.
     */
    // public function createdSalesOrders(): HasMany
    // {
    //     return $this->hasMany(SalesOrder::class, 'created_by');
    // }

    /**
     * Update user balance.
     */
    public function updateBalance(float $amount, string $type = 'credit'): void
    {
        if ($type == 'credit') {
            $this->increment('current_balance', $amount);
        } else {
            $this->decrement('current_balance', $amount);
        }
    }

    /**
     * Get total balance (previous + current).
     */
    public function getTotalBalance(): float
    {
        return $this->previous_due - $this->previous_credit + $this->current_balance;
    }

    /**
     * Check if user has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->getTotalBalance() >= $amount;
    }

    /**
     * Get balance status (positive = credit, negative = due).
     */
    public function getBalanceStatus(): array
    {
        $totalBalance = $this->getTotalBalance();
        
        return [
            'total_balance' => $totalBalance,
            'status' => $totalBalance >= 0 ? 'credit' : 'due',
            'absolute_amount' => abs($totalBalance),
        ];
    }

    /**
     * Get all payments made to this user as supplier.
     */
    public function supplierPayments()
    {
        return Payment::whereHasMorph(
            'paymentable',
            [PurchaseOrder::class],
            function ($query) {
                $query->where('supplier_id', $this->id);
            }
        );
    }

    /**
     * Get all payments made by this user as customer.
     */
    public function customerPayments()
    {
        return Payment::whereHasMorph(
            'paymentable',
            [SalesOrder::class],
            function ($query) {
                $query->where('customer_id', $this->id);
            }
        );
    }

    /**
     * Get all salary records for this employee.
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'employee_id');
    }

    /**
     * Check if user has specific permission (for staff users)
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->user_type === 'admin') {
            return true; // Admin has all permissions
        }

        if ($this->user_type !== 'staff') {
            return false; // Only staff and admin have permissions
        }

        return $this->business?->hasStaffPermission($permission) ?? false;
    }

    /**
     * Get all permissions for this user
     */
    public function getPermissions(): array
    {
        if ($this->user_type === 'admin') {
            return \App\Enums\Permission::getAllPermissions();
        }

        if ($this->user_type !== 'staff') {
            return [];
        }

        return $this->business?->getStaffPermissions() ?? [];
    }
}
