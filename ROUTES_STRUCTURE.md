# Updated API Routes Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication
The API uses Laravel Sanctum for authentication. After login, include the token in the Authorization header:
```
Authorization: Bearer {your_token}
```

## API Endpoints Structure

### Public Routes

#### Register
- **POST** `/register`

#### Login  
- **POST** `/login`

### Protected Routes (require authentication)

#### Auth Management
- **GET** `/auth/profile`
- **PUT** `/auth/profile` 
- **POST** `/auth/change-password`
- **POST** `/auth/logout`
- **POST** `/auth/logout-all`

#### Business Management
- **GET** `/businesses`
- **POST** `/businesses`
- **GET** `/businesses/{business}`
- **PUT** `/businesses/{business}`
- **DELETE** `/businesses/{business}`

#### User Management
- **GET** `/users`
- **POST** `/users`
- **GET** `/users/type/{type}`
- **GET** `/users/{user}`
- **PUT** `/users/{user}`
- **DELETE** `/users/{user}`

#### Categories Management (requires business context)
- **GET** `/categories`
- **POST** `/categories`
- **GET** `/categories/{category}`
- **PUT** `/categories/{category}`
- **DELETE** `/categories/{category}`

#### Brands Management (requires business context)
- **GET** `/brands`
- **POST** `/brands`
- **GET** `/brands/{brand}`
- **PUT** `/brands/{brand}`
- **DELETE** `/brands/{brand}`

#### Units Management (requires business context)
- **GET** `/units`
- **POST** `/units`
- **GET** `/units/{unit}`
- **PUT** `/units/{unit}`
- **DELETE** `/units/{unit}`

#### Products Management (requires business context)
- **GET** `/products`
- **POST** `/products`
- **GET** `/products/low-stock`
- **GET** `/products/{product}`
- **PUT** `/products/{product}`
- **DELETE** `/products/{product}`

## Route Structure Benefits

### 1. Clear Organization
- Routes are grouped by functionality with proper prefixes
- Easy to understand and maintain
- RESTful naming conventions

### 2. Proper Authentication Flow
- Public routes for registration/login
- Protected routes require authentication
- Business context routes require business membership

### 3. Flexible Access Control
- Middleware can be easily applied to route groups
- Permission checks at appropriate levels
- Clear separation of concerns

### 4. Scalability
- Easy to add new route groups
- Consistent structure for future features
- Maintainable codebase

### 5. Documentation Friendly
- Clear endpoint structure
- Predictable URL patterns
- Easy to generate documentation

## Example Usage

### 1. Register and Setup
```bash
# Register as admin
POST /api/register

# Create business  
POST /api/businesses

# Create categories
POST /api/categories

# Create products
POST /api/products
```

### 2. User Management
```bash
# Create supplier
POST /api/users
{
  "user_type": "supplier",
  "name": "ABC Supplier"
}

# Get all suppliers
GET /api/users/type/supplier
```

### 3. Inventory Management
```bash
# List products
GET /api/products

# Check low stock
GET /api/products/low-stock

# Add new product
POST /api/products
```

This route structure provides a solid foundation for the inventory management system with clear organization and proper security controls.
