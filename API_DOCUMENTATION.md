# Inventory Management System API Documentation

## Base URL
```
http://your-domain.com/api
```

## Authentication
This API uses Laravel Sanctum for authentication. Include the Bearer token in the Authorization header for protected routes.

```
Authorization: Bearer {your_token}
```

## Response Format
All API responses follow this format:

```json
{
  "success": true/false,
  "message": "Success/Error message",
  "data": {}, // Response data
  "errors": {} // Validation errors (if any)
}
```

## Public Endpoints

### 1. User Registration
**POST** `/register`

Register a new admin user and create their business.

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "password": "password123",
  "password_confirmation": "password123",
  "business_name": "John's Business"
}
```

### 2. User Login
**POST** `/login`

Login with email/phone and password.

```json
{
  "login": "john@example.com", // email or phone
  "password": "password123"
}
```

## Protected Endpoints

### Authentication Routes

#### Get Profile
**GET** `/profile`

Get current user's profile information.

#### Update Profile
**PUT** `/profile`

```json
{
  "name": "John Doe Updated",
  "email": "john.updated@example.com",
  "phone": "+1234567891",
  "address": "123 Main St"
}
```

#### Change Password
**POST** `/change-password`

```json
{
  "current_password": "oldpassword",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

#### Logout
**POST** `/logout` - Logout from current device
**POST** `/logout-all` - Logout from all devices

### Business Management

#### List Businesses
**GET** `/businesses`

Get all businesses owned by the current user (admin only).

#### Create Business
**POST** `/businesses`

```json
{
  "name": "New Business",
  "address": "123 Business St",
  "phone": "+1234567890",
  "email": "business@example.com",
  "description": "Business description"
}
```

#### Get Business
**GET** `/businesses/{id}`

#### Update Business
**PUT** `/businesses/{id}`

```json
{
  "name": "Updated Business Name",
  "address": "456 New Address",
  "is_active": true
}
```

#### Delete Business
**DELETE** `/businesses/{id}`

### User Management

#### List Users
**GET** `/users`

Query parameters:
- `user_type`: Filter by user type (admin, staff, supplier, retailer, dealer, wholesaler, guest)
- `party_type`: Filter by party type (Regular, Priority)

#### Create User
**POST** `/users`

```json
{
  "name": "Staff User",
  "phone": "+1234567892",
  "email": "staff@example.com",
  "user_type": "staff",
  "party_type": "Regular",
  "password": "password123",
  "password_confirmation": "password123",
  "address": "123 Staff St",
  "previous_due": 100.00,
  "previous_credit": 50.00
}
```

#### Get User
**GET** `/users/{id}`

#### Update User
**PUT** `/users/{id}`

```json
{
  "name": "Updated User Name",
  "phone": "+1234567893",
  "party_type": "Priority"
}
```

#### Delete User
**DELETE** `/users/{id}`

#### Get Users by Type
**GET** `/users/type/{type}`

Get all users of a specific type (supplier, retailer, dealer, wholesaler, guest, staff).

### Category Management

#### List Categories
**GET** `/categories`

#### Create Category
**POST** `/categories`

```json
{
  "name": "Electronics"
}
```

#### Get Category
**GET** `/categories/{id}`

#### Update Category
**PUT** `/categories/{id}`

```json
{
  "name": "Updated Electronics"
}
```

#### Delete Category
**DELETE** `/categories/{id}`

### Brand Management

#### List Brands
**GET** `/brands`

#### Create Brand
**POST** `/brands`

```json
{
  "name": "Samsung"
}
```

#### Get Brand
**GET** `/brands/{id}`

#### Update Brand
**PUT** `/brands/{id}`

```json
{
  "name": "Updated Samsung"
}
```

#### Delete Brand
**DELETE** `/brands/{id}`

### Unit Management

#### List Units
**GET** `/units`

#### Create Unit
**POST** `/units`

```json
{
  "name": "Kilogram",
  "short_name": "kg"
}
```

#### Get Unit
**GET** `/units/{id}`

#### Update Unit
**PUT** `/units/{id}`

```json
{
  "name": "Updated Kilogram",
  "short_name": "kg"
}
```

#### Delete Unit
**DELETE** `/units/{id}`

### Product Management

#### List Products
**GET** `/products`

Query parameters:
- `category_id`: Filter by category
- `brand_id`: Filter by brand
- `search`: Search by name or SKU
- `low_stock`: Set to `true` to get only low stock products

#### Create Product
**POST** `/products`

```json
{
  "name": "iPhone 15",
  "sku": "IPH15-001",
  "category_id": 1,
  "brand_id": 1,
  "unit_id": 1,
  "description": "Latest iPhone model",
  "purchase_price": 800.00,
  "selling_price": 1000.00,
  "quantity": 50,
  "low_stock_threshold": 10,
  "image": "path/to/image.jpg"
}
```

#### Get Product
**GET** `/products/{id}`

#### Update Product
**PUT** `/products/{id}`

```json
{
  "name": "iPhone 15 Pro",
  "selling_price": 1200.00,
  "quantity": 45
}
```

#### Delete Product
**DELETE** `/products/{id}`

#### Get Low Stock Products
**GET** `/products/low-stock`

Get all products where quantity is below or equal to low_stock_threshold.

## User Types and Permissions

### Admin
- Full access to all business features
- Can create/manage staff users
- Can create/manage all contact types (suppliers, retailers, etc.)
- Can manage business settings

### Staff
- Limited access focused on sales operations
- Can create/manage contacts (suppliers, retailers, dealers, wholesalers, guests)
- Cannot create admin or staff users
- Cannot modify business settings

### Contacts (Supplier, Retailer, Dealer, Wholesaler, Guest)
- These are contact records for business operations
- Cannot login to the system (unless password is set)
- Used for purchase orders (suppliers) and sales orders (customers)

## Party Types
- **Regular**: Standard priority contact
- **Priority**: High priority contact for special handling

## Error Codes

- `200`: Success
- `201`: Created successfully
- `400`: Bad request / Validation error
- `401`: Unauthorized (not logged in)
- `403`: Forbidden (insufficient permissions)
- `404`: Not found
- `422`: Validation failed
- `500`: Server error

## Examples

### Complete User Flow

1. **Register as Admin**
```bash
curl -X POST http://your-domain.com/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "business_name": "Johns Electronics"
  }'
```

2. **Login**
```bash
curl -X POST http://your-domain.com/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "login": "john@example.com",
    "password": "password123"
  }'
```

3. **Create Categories, Brands, Units**
```bash
# Create category
curl -X POST http://your-domain.com/api/categories \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name": "Electronics"}'

# Create brand
curl -X POST http://your-domain.com/api/brands \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name": "Apple"}'

# Create unit
curl -X POST http://your-domain.com/api/units \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name": "Piece", "short_name": "pcs"}'
```

4. **Create Product**
```bash
curl -X POST http://your-domain.com/api/products \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "iPhone 15",
    "sku": "IPH15-001",
    "category_id": 1,
    "brand_id": 1,
    "unit_id": 1,
    "purchase_price": 800.00,
    "selling_price": 1000.00,
    "quantity": 50,
    "low_stock_threshold": 10
  }'
```

5. **Create Staff User**
```bash
curl -X POST http://your-domain.com/api/users \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Staff",
    "phone": "+1234567891",
    "email": "jane@example.com",
    "user_type": "staff",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

6. **Create Supplier Contact**
```bash
curl -X POST http://your-domain.com/api/users \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Apple Supplier Inc",
    "phone": "+1234567892",
    "email": "supplier@apple.com",
    "user_type": "supplier",
    "party_type": "Priority",
    "address": "Apple Supplier Address"
  }'
```

This API provides a solid foundation for the inventory management system with proper user roles, business isolation, and comprehensive product management capabilities.
