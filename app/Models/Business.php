<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Business extends Model
{
    
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'logo',
        'description',
        'owner_id',
        'is_active',
        'staff_permissions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'staff_permissions' => 'array',
    ];

    /**
     * Get the owner of the business.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all investors for this business.
     */
    public function investors()
    {
        return $this->hasMany(Investor::class);
    }

    /**
     * Get all users for this business.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all products for this business.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all categories for this business.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get all brands for this business.
     */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * Get all units for this business.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get all purchase orders for this business.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get all sales orders for this business.
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * Get all inventory histories for this business.
     */
    public function inventoryHistories(): HasMany
    {
        return $this->hasMany(InventoryHistory::class);
    }

    /**
     * Get all expense categories for this business.
     */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /**
     * Get all expenses for this business.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get all employee salaries for this business.
     */
    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    /**
     * Get staff members for this business.
     */
    public function staff(): HasMany
    {
        return $this->users()->where('user_type', 'staff');
    }

    /**
     * Check if staff has permission
     */
    public function hasStaffPermission(string $permission): bool
    {
        return in_array($permission, $this->staff_permissions ?? []);
    }

    /**
     * Get all staff permissions
     */
    public function getStaffPermissions(): array
    {
        return $this->staff_permissions ?? [];
    }
}
