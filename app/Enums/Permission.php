<?php

namespace App\Enums;

enum Permission: string
{
    // User Management
    case VIEW_USERS = 'view_users';
    case CREATE_USERS = 'create_users';
    case EDIT_USERS = 'edit_users';
    case DELETE_USERS = 'delete_users';
    
    // Product Management
    case VIEW_PRODUCTS = 'view_products';
    case CREATE_PRODUCTS = 'create_products';
    case EDIT_PRODUCTS = 'edit_products';
    case DELETE_PRODUCTS = 'delete_products';
    case ADJUST_STOCK = 'adjust_stock';
    case VIEW_STOCK_HISTORY = 'view_stock_history';
    
    // Category Management
    case VIEW_CATEGORIES = 'view_categories';
    case CREATE_CATEGORIES = 'create_categories';
    case EDIT_CATEGORIES = 'edit_categories';
    case DELETE_CATEGORIES = 'delete_categories';
    
    // Brand Management
    case VIEW_BRANDS = 'view_brands';
    case CREATE_BRANDS = 'create_brands';
    case EDIT_BRANDS = 'edit_brands';
    case DELETE_BRANDS = 'delete_brands';
    
    // Unit Management
    case VIEW_UNITS = 'view_units';
    case CREATE_UNITS = 'create_units';
    case EDIT_UNITS = 'edit_units';
    case DELETE_UNITS = 'delete_units';
    
    // Purchase Order Management
    case VIEW_PURCHASE_ORDERS = 'view_purchase_orders';
    case CREATE_PURCHASE_ORDERS = 'create_purchase_orders';
    case EDIT_PURCHASE_ORDERS = 'edit_purchase_orders';
    case DELETE_PURCHASE_ORDERS = 'delete_purchase_orders';
    case RECEIVE_SHIPMENTS = 'receive_shipments';
    case MANAGE_PURCHASE_PAYMENTS = 'manage_purchase_payments';
    
    // Sales Order Management
    case VIEW_SALES_ORDERS = 'view_sales_orders';
    case CREATE_SALES_ORDERS = 'create_sales_orders';
    case EDIT_SALES_ORDERS = 'edit_sales_orders';
    case DELETE_SALES_ORDERS = 'delete_sales_orders';
    case SHIP_ORDERS = 'ship_orders';
    case MANAGE_SALES_PAYMENTS = 'manage_sales_payments';
    
    // Payment Management
    case VIEW_PAYMENTS = 'view_payments';
    case CREATE_PAYMENTS = 'create_payments';
    case EDIT_PAYMENTS = 'edit_payments';
    case DELETE_PAYMENTS = 'delete_payments';
    case UPDATE_PAYMENT_STATUS = 'update_payment_status';
    
    // Payment Method Management
    case VIEW_PAYMENT_METHODS = 'view_payment_methods';
    case CREATE_PAYMENT_METHODS = 'create_payment_methods';
    case EDIT_PAYMENT_METHODS = 'edit_payment_methods';
    case DELETE_PAYMENT_METHODS = 'delete_payment_methods';
    
    // Balance Management
    case VIEW_USER_BALANCES = 'view_user_balances';
    case ADJUST_USER_BALANCES = 'adjust_user_balances';
    case VIEW_BALANCE_HISTORY = 'view_balance_history';
    
    // Expense Management
    case VIEW_EXPENSES = 'view_expenses';
    case CREATE_EXPENSES = 'create_expenses';
    case EDIT_EXPENSES = 'edit_expenses';
    case DELETE_EXPENSES = 'delete_expenses';
    case VIEW_EXPENSE_REPORTS = 'view_expense_reports';
    
    // Expense Category Management
    case VIEW_EXPENSE_CATEGORIES = 'view_expense_categories';
    case CREATE_EXPENSE_CATEGORIES = 'create_expense_categories';
    case EDIT_EXPENSE_CATEGORIES = 'edit_expense_categories';
    case DELETE_EXPENSE_CATEGORIES = 'delete_expense_categories';
    
    // Salary Management
    case VIEW_SALARIES = 'view_salaries';
    case CREATE_SALARIES = 'create_salaries';
    case EDIT_SALARIES = 'edit_salaries';
    case DELETE_SALARIES = 'delete_salaries';
    case VIEW_SALARY_REPORTS = 'view_salary_reports';
    
    // Business Management
    case VIEW_BUSINESS_SETTINGS = 'view_business_settings';
    case EDIT_BUSINESS_SETTINGS = 'edit_business_settings';
    case MANAGE_STAFF_PERMISSIONS = 'manage_staff_permissions';
    
    // Reports and Analytics
    case VIEW_FINANCIAL_REPORTS = 'view_financial_reports';
    case VIEW_INVENTORY_REPORTS = 'view_inventory_reports';
    case VIEW_SALES_REPORTS = 'view_sales_reports';
    case VIEW_PURCHASE_REPORTS = 'view_purchase_reports';    case EXPORT_REPORTS = 'export_reports';
    
    /**
     * Get all permissions grouped by module (alias for getAllGrouped)
     */
    public static function getGroupedPermissions(): array
    {
        return self::getAllGrouped();
    }

    /**
     * Get all permission values as an array (alias for getAll)
     */
    public static function getAllPermissions(): array
    {
        return self::getAll();
    }

    /**
     * Get all permissions grouped by category
     */
    public static function getAllGrouped(): array
    {
        return [
            'User Management' => [
                self::VIEW_USERS,
                self::CREATE_USERS,
                self::EDIT_USERS,
                self::DELETE_USERS,
            ],
            'Product Management' => [
                self::VIEW_PRODUCTS,
                self::CREATE_PRODUCTS,
                self::EDIT_PRODUCTS,
                self::DELETE_PRODUCTS,
                self::ADJUST_STOCK,
                self::VIEW_STOCK_HISTORY,
            ],
            'Category Management' => [
                self::VIEW_CATEGORIES,
                self::CREATE_CATEGORIES,
                self::EDIT_CATEGORIES,
                self::DELETE_CATEGORIES,
            ],
            'Brand Management' => [
                self::VIEW_BRANDS,
                self::CREATE_BRANDS,
                self::EDIT_BRANDS,
                self::DELETE_BRANDS,
            ],
            'Unit Management' => [
                self::VIEW_UNITS,
                self::CREATE_UNITS,
                self::EDIT_UNITS,
                self::DELETE_UNITS,
            ],
            'Purchase Order Management' => [
                self::VIEW_PURCHASE_ORDERS,
                self::CREATE_PURCHASE_ORDERS,
                self::EDIT_PURCHASE_ORDERS,
                self::DELETE_PURCHASE_ORDERS,
                self::RECEIVE_SHIPMENTS,
                self::MANAGE_PURCHASE_PAYMENTS,
            ],
            'Sales Order Management' => [
                self::VIEW_SALES_ORDERS,
                self::CREATE_SALES_ORDERS,
                self::EDIT_SALES_ORDERS,
                self::DELETE_SALES_ORDERS,
                self::SHIP_ORDERS,
                self::MANAGE_SALES_PAYMENTS,
            ],
            'Payment Management' => [
                self::VIEW_PAYMENTS,
                self::CREATE_PAYMENTS,
                self::EDIT_PAYMENTS,
                self::DELETE_PAYMENTS,
                self::UPDATE_PAYMENT_STATUS,
            ],
            'Payment Method Management' => [
                self::VIEW_PAYMENT_METHODS,
                self::CREATE_PAYMENT_METHODS,
                self::EDIT_PAYMENT_METHODS,
                self::DELETE_PAYMENT_METHODS,
            ],
            'Balance Management' => [
                self::VIEW_USER_BALANCES,
                self::ADJUST_USER_BALANCES,
                self::VIEW_BALANCE_HISTORY,
            ],
            'Expense Management' => [
                self::VIEW_EXPENSES,
                self::CREATE_EXPENSES,
                self::EDIT_EXPENSES,
                self::DELETE_EXPENSES,
                self::VIEW_EXPENSE_REPORTS,
            ],
            'Expense Category Management' => [
                self::VIEW_EXPENSE_CATEGORIES,
                self::CREATE_EXPENSE_CATEGORIES,
                self::EDIT_EXPENSE_CATEGORIES,
                self::DELETE_EXPENSE_CATEGORIES,
            ],
            'Salary Management' => [
                self::VIEW_SALARIES,
                self::CREATE_SALARIES,
                self::EDIT_SALARIES,
                self::DELETE_SALARIES,
                self::VIEW_SALARY_REPORTS,
            ],
            'Business Management' => [
                self::VIEW_BUSINESS_SETTINGS,
                self::EDIT_BUSINESS_SETTINGS,
                self::MANAGE_STAFF_PERMISSIONS,
            ],
            'Reports and Analytics' => [
                self::VIEW_FINANCIAL_REPORTS,
                self::VIEW_INVENTORY_REPORTS,
                self::VIEW_SALES_REPORTS,
                self::VIEW_PURCHASE_REPORTS,
                self::EXPORT_REPORTS,
            ],
        ];
    }

    /**
     * Get all permissions as simple array
     */
    public static function getAll(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get permission label for display
     */
    public function getLabel(): string
    {
        return match($this) {
            self::VIEW_USERS => 'View Users',
            self::CREATE_USERS => 'Create Users',
            self::EDIT_USERS => 'Edit Users',
            self::DELETE_USERS => 'Delete Users',
            
            self::VIEW_PRODUCTS => 'View Products',
            self::CREATE_PRODUCTS => 'Create Products',
            self::EDIT_PRODUCTS => 'Edit Products',
            self::DELETE_PRODUCTS => 'Delete Products',
            self::ADJUST_STOCK => 'Adjust Stock',
            self::VIEW_STOCK_HISTORY => 'View Stock History',
            
            self::VIEW_CATEGORIES => 'View Categories',
            self::CREATE_CATEGORIES => 'Create Categories',
            self::EDIT_CATEGORIES => 'Edit Categories',
            self::DELETE_CATEGORIES => 'Delete Categories',
            
            self::VIEW_BRANDS => 'View Brands',
            self::CREATE_BRANDS => 'Create Brands',
            self::EDIT_BRANDS => 'Edit Brands',
            self::DELETE_BRANDS => 'Delete Brands',
            
            self::VIEW_UNITS => 'View Units',
            self::CREATE_UNITS => 'Create Units',
            self::EDIT_UNITS => 'Edit Units',
            self::DELETE_UNITS => 'Delete Units',
            
            self::VIEW_PURCHASE_ORDERS => 'View Purchase Orders',
            self::CREATE_PURCHASE_ORDERS => 'Create Purchase Orders',
            self::EDIT_PURCHASE_ORDERS => 'Edit Purchase Orders',
            self::DELETE_PURCHASE_ORDERS => 'Delete Purchase Orders',
            self::RECEIVE_SHIPMENTS => 'Receive Shipments',
            self::MANAGE_PURCHASE_PAYMENTS => 'Manage Purchase Payments',
            
            self::VIEW_SALES_ORDERS => 'View Sales Orders',
            self::CREATE_SALES_ORDERS => 'Create Sales Orders',
            self::EDIT_SALES_ORDERS => 'Edit Sales Orders',
            self::DELETE_SALES_ORDERS => 'Delete Sales Orders',
            self::SHIP_ORDERS => 'Ship Orders',
            self::MANAGE_SALES_PAYMENTS => 'Manage Sales Payments',
            
            self::VIEW_PAYMENTS => 'View Payments',
            self::CREATE_PAYMENTS => 'Create Payments',
            self::EDIT_PAYMENTS => 'Edit Payments',
            self::DELETE_PAYMENTS => 'Delete Payments',
            self::UPDATE_PAYMENT_STATUS => 'Update Payment Status',
            
            self::VIEW_PAYMENT_METHODS => 'View Payment Methods',
            self::CREATE_PAYMENT_METHODS => 'Create Payment Methods',
            self::EDIT_PAYMENT_METHODS => 'Edit Payment Methods',
            self::DELETE_PAYMENT_METHODS => 'Delete Payment Methods',
            
            self::VIEW_USER_BALANCES => 'View User Balances',
            self::ADJUST_USER_BALANCES => 'Adjust User Balances',
            self::VIEW_BALANCE_HISTORY => 'View Balance History',
            
            self::VIEW_EXPENSES => 'View Expenses',
            self::CREATE_EXPENSES => 'Create Expenses',
            self::EDIT_EXPENSES => 'Edit Expenses',
            self::DELETE_EXPENSES => 'Delete Expenses',
            self::VIEW_EXPENSE_REPORTS => 'View Expense Reports',
            
            self::VIEW_EXPENSE_CATEGORIES => 'View Expense Categories',
            self::CREATE_EXPENSE_CATEGORIES => 'Create Expense Categories',
            self::EDIT_EXPENSE_CATEGORIES => 'Edit Expense Categories',
            self::DELETE_EXPENSE_CATEGORIES => 'Delete Expense Categories',
            
            self::VIEW_SALARIES => 'View Salaries',
            self::CREATE_SALARIES => 'Create Salaries',
            self::EDIT_SALARIES => 'Edit Salaries',
            self::DELETE_SALARIES => 'Delete Salaries',
            self::VIEW_SALARY_REPORTS => 'View Salary Reports',
            
            self::VIEW_BUSINESS_SETTINGS => 'View Business Settings',
            self::EDIT_BUSINESS_SETTINGS => 'Edit Business Settings',
            self::MANAGE_STAFF_PERMISSIONS => 'Manage Staff Permissions',
            
            self::VIEW_FINANCIAL_REPORTS => 'View Financial Reports',
            self::VIEW_INVENTORY_REPORTS => 'View Inventory Reports',
            self::VIEW_SALES_REPORTS => 'View Sales Reports',
            self::VIEW_PURCHASE_REPORTS => 'View Purchase Reports',
            self::EXPORT_REPORTS => 'Export Reports',
        };
    }
}
