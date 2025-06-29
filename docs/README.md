# Inventory Management System Documentation

Welcome to the Inventory Management System documentation. This folder contains all the technical documentation for the project.

## Documentation Index

### 📋 Database Design
- **[DATABASE_DESIGN.md](DATABASE_DESIGN.md)** - Complete database schema design, table structures, and relationships

### 🚀 API Documentation
- **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** - Comprehensive API endpoints documentation with examples

## Quick Start

1. **Database Setup**: Review the database design in `DATABASE_DESIGN.md`
2. **API Usage**: Check `API_DOCUMENTATION.md` for all available endpoints
3. **Authentication**: Start with the auth endpoints to register/login users
4. **Business Setup**: Create a business after authentication
5. **User Management**: Add staff and contacts (suppliers, retailers, etc.)

## Project Structure

```
inventory-management-system/
├── docs/
│   ├── README.md (this file)
│   ├── DATABASE_DESIGN.md
│   └── API_DOCUMENTATION.md
├── app/
│   ├── Models/
│   ├── Http/Controllers/Api/
│   └── ...
├── database/
│   ├── migrations/
│   └── seeders/
└── routes/
    └── api.php
```

## Key Features

- **Multi-tenant Architecture**: Each admin has their own business with isolated data
- **User Management**: Admin, Staff, and various party types (Suppliers, Retailers, etc.)
- **Role-based Access**: Different permissions for admin and staff users
- **RESTful API**: Clean and consistent API design
- **Authentication**: Sanctum-based token authentication

## Technology Stack

- **Backend**: Laravel 11
- **Database**: MySQL/SQLite
- **Authentication**: Laravel Sanctum
- **API**: RESTful JSON API
- **Documentation**: Markdown

## Support

For technical questions or issues, please refer to the specific documentation files or contact the development team.
