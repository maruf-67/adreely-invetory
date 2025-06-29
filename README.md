# Inventory Management System

A comprehensive multi-tenant inventory management system built with Laravel 11. This system allows multiple businesses to manage their inventory, users, suppliers, customers, and sales/purchase orders independently.

## 🚀 Features

- **Multi-tenant Architecture**: Each admin manages their own isolated business
- **User Management**: Admin, Staff, Suppliers, Retailers, Dealers, Wholesalers, and Guests
- **Role-based Access Control**: Different permissions for different user types
- **Inventory Tracking**: Complete stock management with history
- **Purchase Orders**: Manage orders from suppliers
- **Sales Orders**: Handle customer orders and invoicing
- **RESTful API**: Clean JSON API for all operations
- **Authentication**: Secure token-based authentication

## 📚 Documentation

Complete documentation is available in the `docs` folder:

- **[docs/README.md](docs/README.md)** - Documentation index and quick start guide
- **[docs/DATABASE_DESIGN.md](docs/DATABASE_DESIGN.md)** - Database schema and relationships
- **[docs/API_DOCUMENTATION.md](docs/API_DOCUMENTATION.md)** - Complete API reference

## 🛠️ Technology Stack

- **Backend**: Laravel 11
- **Database**: MySQL/SQLite
- **Authentication**: Laravel Sanctum
- **API**: RESTful JSON API
- **Permissions**: Spatie Laravel Permission

## 🚀 Quick Start

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd inventory-management-system
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Start the server**
   ```bash
   php artisan serve
   ```

## 📖 API Usage

The API is available at `http://localhost:8000/api/`

### Authentication
```bash
# Register a new admin
POST /api/auth/register

# Login
POST /api/auth/login

# Get profile
GET /api/auth/profile
```

### Business Management
```bash
# Create business
POST /api/businesses

# Get businesses
GET /api/businesses
```

For complete API documentation, see [docs/API_DOCUMENTATION.md](docs/API_DOCUMENTATION.md)

## 🏗️ Project Structure

```
├── app/
│   ├── Http/Controllers/Api/  # API Controllers
│   ├── Models/               # Eloquent Models
│   └── ...
├── database/
│   ├── migrations/          # Database migrations
│   └── seeders/            # Database seeders
├── docs/                   # Documentation
├── routes/
│   └── api.php            # API routes
└── ...
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development/)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
