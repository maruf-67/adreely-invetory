# 🚀 Laravel API Test Data Setup

This script will refresh your database and populate it with comprehensive test data for all the enhanced APIs.

## Quick Setup Commands

### Option 1: Full Refresh with Comprehensive Test Data (Recommended)
```bash
# Navigate to project directory
cd /var/www/laravel/work

# Refresh database and run comprehensive seeder
php artisan migrate:fresh --seed
```

### Option 2: Manual Step-by-Step
```bash
# 1. Drop all tables and recreate
php artisan migrate:fresh

# 2. Run comprehensive seeder only
php artisan db:seed --class=ComprehensiveTestDataSeeder

# 3. Or run specific seeder if needed
php artisan db:seed --class=DatabaseSeeder
```

### Option 3: Quick Reset (if database already has structure)
```bash
# Clear data and reseed
php artisan db:wipe
php artisan migrate
php artisan db:seed --class=ComprehensiveTestDataSeeder
```

---

## 🎯 What Gets Created

### 3 Complete Businesses:
1. **TechMart Electronics** - Electronics retail business
2. **FreshMart Groceries** - Grocery retail business  
3. **AutoParts Central** - Automotive parts business

### Users for Each Business:
- **1 Admin** - Full system access
- **3 Staff Members** - Employees with salary data
- **3 Suppliers** - Vendor companies with various balance states
- **4 Customers** - Mix of business and individual customers
- **3 Partners** - Wholesalers, dealers, retailers

### Master Data:
- **Categories** - Industry-specific product categories
- **Brands** - Relevant brands for each business type
- **Units** - Measurement units (piece, kg, liter, box, set)
- **Payment Methods** - Cash, bank transfer, credit card, etc.
- **Expense Categories** - Business expense types

### Transaction Data:
- **Products** - Industry-specific products with proper inventory
- **Purchase Orders** - 3-5 orders per business with shipments
- **Sales Orders** - 4-7 orders per business with various statuses
- **Payments** - Realistic payment patterns
- **Expenses** - 8-12 expense records per business
- **Employee Salaries** - 3 months of salary history
- **Investors** - 2-3 investor records per business
- **User Balances** - Realistic balance adjustments

---

## 🔑 Login Credentials

### Admin Accounts:
```
TechMart Electronics:
  Email: admin@techmartelectronics.com
  Password: password123

FreshMart Groceries:
  Email: admin@freshmartgroceries.com
  Password: password123

AutoParts Central:
  Email: admin@autopartscentral.com
  Password: password123
```

### Staff Accounts (for each business):
```
Manager: john.manager@[businessname].com
Clerk: sarah.clerk@[businessname].com
Warehouse: mike.warehouse@[businessname].com
Password: password123
```

---

## 🧪 Perfect for Testing These APIs:

### ✅ Enhanced User Balance Summary
- Test grouping by user_type (staff, supplier, customer, etc.)
- Various balance states (credit, due, balanced)
- Multiple user types per business

### ✅ New Report Endpoints
- **Sales Report**: Orders with various statuses and date ranges
- **Purchase Report**: Purchase data with payment patterns
- **Inventory Report**: Stock movements from orders and adjustments
- **Profit & Loss**: Revenue vs expenses with realistic margins

### ✅ Enhanced Order Workflows
- **Sales Orders**: Test new pending → ship → complete flow
- **Purchase Orders**: Complete workflow with inventory updates
- **Various Statuses**: pending, partial, completed orders

### ✅ Employee Management
- **Staff with Salary Data**: join_date and salary_amount fields
- **Salary History**: 3 months of payment records
- **Employee Types**: Different roles and salary levels

### ✅ Inventory Management
- **Stock Movements**: Purchase receipts, sales shipments
- **Stock Adjustments**: Manual inventory corrections
- **Low Stock Alerts**: Products below threshold levels

### ✅ User Balance Features
- **Balance Grouping**: Users grouped by type with statistics
- **Balance History**: Transaction history with references
- **Manual Adjustments**: Balance corrections and notes

---

## 📊 Sample Data Volumes

### Per Business:
- **Users**: 12+ (1 admin, 3 staff, 3 suppliers, 4 customers, 3 partners)
- **Products**: 2-5 industry-specific products
- **Purchase Orders**: 3-5 with realistic item quantities
- **Sales Orders**: 4-7 with various completion states
- **Expenses**: 8-12 across different categories
- **Employee Salaries**: 9+ records (3 employees × 3 months)
- **Investors**: 2-3 investment records
- **Balance Adjustments**: Random adjustments for testing

### Total Across All Businesses:
- **36+ Users** of all types
- **15+ Products** with inventory data
- **21+ Orders** (purchase + sales)
- **30+ Expense Records**
- **27+ Salary Records**
- **9+ Investor Records**
- **Hundreds of transaction records**

---

## 🎯 Ready-to-Test Scenarios

1. **Login with different user types** (admin, staff, supplier, customer)
2. **Test user balance summary** with grouping by user_type
3. **Generate reports** with various date ranges
4. **Create new sales orders** using the simplified workflow
5. **Process inventory** with purchase orders and stock adjustments
6. **Manage employee data** with salary information
7. **View financial data** with profit/loss reports
8. **Test balance management** across different user types

---

## 🚨 Important Notes

- **Backup First**: This will completely refresh your database
- **Test Environment**: Use only in development/testing environments
- **Realistic Data**: All amounts, dates, and relationships are realistic
- **Business Logic**: Data follows proper business rules and workflows
- **API Ready**: Designed specifically for testing the enhanced APIs

---

## 🔄 Re-running Seeders

If you need fresh data anytime:
```bash
php artisan migrate:fresh --seed
```

Or just the seeder:
```bash
php artisan db:seed --class=ComprehensiveTestDataSeeder
```

---

**Happy Testing! 🎉**

All your enhanced APIs now have comprehensive, realistic test data to work with.
