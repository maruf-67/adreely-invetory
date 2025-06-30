# New Entities Implementation Documentation

This document provides comprehensive information about the newly implemented entities in the Laravel Inventory Management System.

## Overview

Four new entities have been successfully implemented:
1. **Investors** - Manage business investors
2. **Investments** - Track investment records
3. **Extra Income Types** - Categorize additional income sources
4. **Extra Incomes** - Record extra income transactions

## Database Schema

### Investors Table
```sql
- id (bigint, primary key)
- business_id (bigint, foreign key to businesses)
- name (varchar 255)
- contact_info (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

### Investments Table
```sql
- id (bigint, primary key)
- business_id (bigint, foreign key to businesses)
- investor_id (bigint, foreign key to investors)
- amount (decimal 15,2)
- date (date)
- description (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

### Extra Income Types Table
```sql
- id (bigint, primary key)
- business_id (bigint, foreign key to businesses)
- name (varchar 255)
- created_at (timestamp)
- updated_at (timestamp)
```

### Extra Incomes Table
```sql
- id (bigint, primary key)
- business_id (bigint, foreign key to businesses)
- income_type_id (bigint, foreign key to extra_income_types)
- amount (decimal 15,2)
- date (date)
- description (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

## Models

### Investor Model
- **Location**: `app/Models/Investor.php`
- **Relationships**:
  - `belongsTo(Business::class)` - business
  - `hasMany(Investment::class)` - investments
- **Mass Assignable**: `name`, `contact_info`, `business_id`

### Investment Model
- **Location**: `app/Models/Investment.php`
- **Relationships**:
  - `belongsTo(Business::class)` - business
  - `belongsTo(Investor::class)` - investor
- **Mass Assignable**: `investor_id`, `amount`, `date`, `description`, `business_id`
- **Casts**: `date` as date, `amount` as decimal:2

### ExtraIncomeType Model
- **Location**: `app/Models/ExtraIncomeType.php`
- **Relationships**:
  - `belongsTo(Business::class)` - business
  - `hasMany(ExtraIncome::class, 'income_type_id')` - extraIncomes
- **Mass Assignable**: `name`, `business_id`

### ExtraIncome Model
- **Location**: `app/Models/ExtraIncome.php`
- **Relationships**:
  - `belongsTo(Business::class)` - business
  - `belongsTo(ExtraIncomeType::class, 'income_type_id')` - incomeType
- **Mass Assignable**: `income_type_id`, `amount`, `date`, `description`, `business_id`
- **Casts**: `date` as date, `amount` as decimal:2

## API Controllers

### InvestmentController
- **Location**: `app/Http/Controllers/Api/InvestmentController.php`
- **Endpoints**:
  - `GET /api/investments` - List investments with filtering
  - `POST /api/investments` - Create new investment
  - `GET /api/investments/summary` - Get investment summary
  - `GET /api/investments/{id}` - Show specific investment
  - `PUT /api/investments/{id}` - Update investment
  - `DELETE /api/investments/{id}` - Delete investment

### InvestorController
- **Location**: `app/Http/Controllers/Api/InvestorController.php`
- **Endpoints**:
  - `GET /api/investors` - List investors with search
  - `POST /api/investors` - Create new investor
  - `GET /api/investors/{id}` - Show specific investor
  - `PUT /api/investors/{id}` - Update investor
  - `DELETE /api/investors/{id}` - Delete investor

### ExtraIncomeController
- **Location**: `app/Http/Controllers/Api/ExtraIncomeController.php`
- **Endpoints**:
  - `GET /api/extra-incomes` - List extra incomes with filtering
  - `POST /api/extra-incomes` - Create new extra income
  - `GET /api/extra-incomes/summary` - Get extra income summary
  - `GET /api/extra-incomes/{id}` - Show specific extra income
  - `PUT /api/extra-incomes/{id}` - Update extra income
  - `DELETE /api/extra-incomes/{id}` - Delete extra income

### ExtraIncomeTypeController
- **Location**: `app/Http/Controllers/Api/ExtraIncomeTypeController.php`
- **Endpoints**:
  - `GET /api/extra-income-types` - List extra income types
  - `POST /api/extra-income-types` - Create new extra income type
  - `GET /api/extra-income-types/{id}` - Show specific extra income type
  - `PUT /api/extra-income-types/{id}` - Update extra income type
  - `DELETE /api/extra-income-types/{id}` - Delete extra income type

## API Features

### Common Features Across All Controllers
- **Authentication**: All endpoints require `auth:sanctum` middleware
- **Business Scoping**: All operations are scoped to user's business
- **Validation**: Comprehensive input validation
- **Error Handling**: Consistent error responses
- **JSON Responses**: Standardized response format

### Filtering and Search
- **Investments**: Filter by investor, date range, amount range
- **Investors**: Search by name and contact info
- **Extra Incomes**: Filter by income type, date range, search description
- **Extra Income Types**: Search by name

### Summary Endpoints
- **Investment Summary**: Total investments, count by investor, monthly breakdown
- **Extra Income Summary**: Total by income type, monthly trends, yearly comparison

## Sample Data

The `NewEntitiesSeeder` creates sample data for all entities:
- 5 investors per business
- 10 investments per business
- 3 extra income types per business
- 15 extra incomes per business

## Usage Examples

### Creating an Investor
```json
POST /api/investors
{
    "name": "John Smith",
    "contact_info": "john@example.com, +1234567890"
}
```

### Creating an Investment
```json
POST /api/investments
{
    "investor_id": 1,
    "amount": 50000.00,
    "date": "2025-06-30",
    "description": "Series A funding"
}
```

### Creating Extra Income Type
```json
POST /api/extra-income-types
{
    "name": "Consulting Services"
}
```

### Creating Extra Income
```json
POST /api/extra-incomes
{
    "income_type_id": 1,
    "amount": 5000.00,
    "date": "2025-06-30",
    "description": "Software consulting project"
}
```

## Validation Rules

### Investment
- `investor_id`: required, must exist in investors table
- `amount`: required, numeric, minimum 0.01
- `date`: required, valid date
- `description`: optional, string, max 1000 characters

### Investor
- `name`: required, string, max 255 characters
- `contact_info`: optional, string, max 1000 characters

### Extra Income Type
- `name`: required, string, max 255 characters

### Extra Income
- `income_type_id`: required, must exist in extra_income_types table
- `amount`: required, numeric, minimum 0.01
- `date`: required, valid date
- `description`: optional, string, max 1000 characters

## Database Migrations

All migrations are located in `/database/migrations/`:
- `2025_06_30_120004_create_investors_table.php`
- `2025_06_30_120005_create_investments_table.php`
- `2025_06_30_120006_create_extra_income_types_table.php`
- `2025_06_30_120007_create_extra_incomes_table.php`

## Testing the APIs

All endpoints can be tested using tools like Postman or curl. Make sure to:
1. Register/login to get authentication token
2. Include `Authorization: Bearer {token}` header
3. Set `Content-Type: application/json` for POST/PUT requests
4. Use proper HTTP methods (GET, POST, PUT, DELETE)

## Integration Notes

- All new entities are fully integrated with the existing business model
- Foreign key constraints ensure data integrity
- Soft deletes are not implemented (can be added if needed)
- All controllers follow the same patterns as existing controllers
- API routes are properly organized and documented

## Performance Considerations

- Database indexes are added on foreign keys
- Eager loading is used to prevent N+1 queries
- Pagination is implemented for list endpoints
- Summary queries are optimized with proper aggregations

The implementation is complete and ready for production use.
