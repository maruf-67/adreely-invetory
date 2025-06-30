# API Testing Guide - New Entities

## Quick Setup for Testing

### 1. Start the Laravel Server
```bash
cd /var/www/laravel/work
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Register a Test User
```bash
curl -X POST http://127.0.0.1:8000/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com", 
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 3. Login to Get Token
```bash
curl -X POST http://127.0.0.1:8000/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

### 4. Test New Endpoints (Replace YOUR_TOKEN with actual token)

#### Test Investors
```bash
# List investors
curl -X GET http://127.0.0.1:8000/api/investors \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Create investor
curl -X POST http://127.0.0.1:8000/api/investors \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Smith",
    "contact_info": "john@example.com, +1234567890"
  }'
```

#### Test Investments
```bash
# List investments
curl -X GET http://127.0.0.1:8000/api/investments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Create investment (use actual investor_id from previous step)
curl -X POST http://127.0.0.1:8000/api/investments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "investor_id": 1,
    "amount": 50000.00,
    "date": "2025-06-30",
    "description": "Series A funding"
  }'

# Get investment summary
curl -X GET http://127.0.0.1:8000/api/investments/summary \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

#### Test Extra Income Types
```bash
# List extra income types
curl -X GET http://127.0.0.1:8000/api/extra-income-types \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Create extra income type
curl -X POST http://127.0.0.1:8000/api/extra-income-types \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Consulting Services"
  }'
```

#### Test Extra Incomes
```bash
# List extra incomes
curl -X GET http://127.0.0.1:8000/api/extra-incomes \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Create extra income (use actual income_type_id from previous step)
curl -X POST http://127.0.0.1:8000/api/extra-incomes \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "income_type_id": 1,
    "amount": 5000.00,
    "date": "2025-06-30",
    "description": "Software consulting project"
  }'

# Get extra income summary
curl -X GET http://127.0.0.1:8000/api/extra-incomes/summary \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## Sample Response Formats

### Successful Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }
}
```

## Available Data

The database has been seeded with sample data:
- Multiple investors per business
- Investment records
- Extra income types (Consulting, Freelancing, Commission)
- Extra income records

You can use the seeded data IDs in your API tests or create new records.

## Postman Collection

The existing Postman collection (`Inventory Management.postman_collection.json`) can be extended with these new endpoints following the same authentication patterns.
