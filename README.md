# 🧾 Billing System - FactuGriego

Complete billing system developed in Laravel with REST API for customer, product, invoice, and report management.

> **📖 [Ver en Español](README_ES.md)** | **🇺🇸 View in English**

Complete billing system developed in Laravel with REST API for customer, product, invoice, and report management.

## 📋 Table of Contents

- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [System Usage](#system-usage)
- [API Endpoints](#api-endpoints)
- [Project Structure](#project-structure)
- [Useful Commands](#useful-commands)
- [Troubleshooting](#troubleshooting)

## 🖥️ System Requirements

### Required Software
- **PHP**: 8.1 or higher
- **Composer**: 2.0 or higher
- **MySQL**: 8.0 or higher
- **Node.js**: 16.0 or higher (optional, for development)
- **Git**: To clone the repository

### Required PHP Extensions
```bash
php -m | grep -E "(bcmath|ctype|fileinfo|json|mbstring|openssl|pdo|tokenizer|xml)"
```

## 🚀 Installation

### Step 1: Clone Repository
```bash
git clone <repository-url>
cd billing_system_core
```

### Step 2: Install PHP Dependencies
```bash
composer install
```

### Step 3: Configure Environment Variables
```bash
cp .env.example .env
```

Edit the `.env` file with your configuration:
```env
APP_NAME="Billing System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_system_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 4: Generate Application Key
```bash
php artisan key:generate
```

### Step 5: Create Database
```sql
CREATE DATABASE billing_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 6: Run Migrations
```bash
php artisan migrate
```

### Step 7: Insert Initial Data
```bash
php artisan data:insert
```

### Step 8: Clean Unused Tables (Optional)
```bash
php artisan system:cleanup-tables
```

### Step 9: Configure Storage Permissions
```bash
chmod -R 775 storage bootstrap/cache
```

### Step 10: Start Server
```bash
php artisan serve
```

The system will be available at: `http://localhost:8000`

## ⚙️ Configuration

### Email Configuration (SMTP)
For the system to send invoices by email:

1. **Configure SMTP Settings in Database:**
   The system uses database settings for email configuration. After installation, configure SMTP settings through the web interface:
   - Go to **Settings > Email Configuration**
   - Enter your SMTP server details
   - For Gmail, use:
     - Host: smtp.gmail.com
     - Port: 587
     - Encryption: TLS
     - Use App Password (not regular password)

2. **Test Configuration:**
   ```bash
   php artisan test-email
   ```

### Company Configuration
After installation, configure company information:

1. Access the system with: `admin` / `password`
2. Go to Configuration → Company Information
3. Update company data

## 👥 System Usage

### Default Users
- **Administrator**: `admin` / `password`
- **Invoice User**: `facturador` / `password`

### System Roles
- **Administrator**: Full system access
- **Invoice User**: Only invoicing functions

### Main Features
- ✅ Customer Management
- ✅ Product/Service Management
- ✅ Invoice Creation and Issuance
- ✅ PDF Generation
- ✅ Invoice Email Sending
- ✅ Sales Reports
- ✅ User Audit
- ✅ System Configuration

## 🔌 API Endpoints

### Authentication
```http
POST /api/login
Content-Type: application/json

{
    "username": "admin",
    "password": "password"
}
```

### Customers
```http
GET    /api/customers              # List customers
GET    /api/customers/{id}         # Get customer
POST   /api/customers              # Create customer
PUT    /api/customers/{id}         # Update customer
DELETE /api/customers/{id}         # Delete customer
```

### Products/Services
```http
GET    /api/products-services              # List products
GET    /api/products-services/{id}         # Get product
POST   /api/products-services              # Create product
PUT    /api/products-services/{id}         # Update product
DELETE /api/products-services/{id}         # Delete product
```

### Invoices
```http
GET    /api/invoices               # List invoices
GET    /api/invoices/{id}          # Get invoice
POST   /api/invoices               # Create invoice
PUT    /api/invoices/{id}          # Update invoice
DELETE /api/invoices/{id}          # Delete invoice
POST   /api/invoices/{id}/issue    # Issue invoice
POST   /api/invoices/{id}/cancel   # Cancel invoice
GET    /api/invoices/{id}/pdf      # Generate PDF
POST   /api/invoices/{id}/send-email # Send by email
```

### Reports
```http
GET /api/reports/sales             # Sales report
GET /api/reports/customers         # Customer report
GET /api/reports/products          # Product report
GET /api/reports/monthly-sales     # Monthly sales
```

### Users (Administrators Only)
```http
GET    /api/users                  # List users
GET    /api/users/{id}             # Get user
POST   /api/users                  # Create user
PUT    /api/users/{id}             # Update user
DELETE /api/users/{id}             # Delete user
```

### System
```http
GET  /api/system/info              # System information
GET  /api/system/settings          # Settings
PUT  /api/system/settings          # Update settings
GET  /api/system/health            # System status
```

### Audit (Administrators Only)
```http
GET /api/audit/movements           # Movement logs
GET /api/audit/logins              # Login logs
GET /api/audit/users               # User information
GET /api/audit/statistics          # Statistics
```

## 📁 Project Structure

```
billing_system_core/
├── app/
│   ├── Http/Controllers/Api/     # API Controllers
│   ├── Models/                   # Eloquent Models
│   ├── Helpers/                  # Custom Helpers
│   └── Traits/                   # Reusable Traits
├── database/
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Seeders (if any)
├── routes/
│   └── api.php                   # API routes
├── Console/Commands/             # Custom commands
└── storage/
    └── logs/                     # System logs
```

## 🛠️ Useful Commands

### Installation Commands
```bash
php artisan data:insert              # Insert initial data
php artisan system:cleanup-tables    # Clean unused tables
php artisan migrate:fresh            # Recreate database
```

### Diagnostic Commands
```bash
php artisan test-email               # Test email configuration
php artisan system:health            # Check system status
php artisan route:list               # List all routes
```

### Maintenance Commands
```bash
php artisan cache:clear              # Clear cache
php artisan config:clear             # Clear configuration
php artisan view:clear               # Clear views
```

## 🔧 Troubleshooting

### Database Connection Error
```bash
# Check configuration
php artisan tinker
>>> DB::connection()->getPdo();
```

### Permission Error
```bash
# On Linux/Mac
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Email Error
```bash
# Test SMTP configuration
php artisan test-email
```

### Migration Error
```bash
# Recreate database
php artisan migrate:fresh --seed
```

### Authentication Error
```bash
# Regenerate application key
php artisan key:generate
```

## 📊 System Features

### ✅ Implemented Features
- Sanctum authentication system
- Role and permission management
- Multitenancy (multiple companies)
- PDF generation with TCPDF
- Email sending with templates
- Complete action audit
- Documented REST API
- Detailed logging system

### 🔒 Security
- JWT authentication with Sanctum
- Data validation on all endpoints
- Company filtering (multitenancy)
- Audit logs
- CSRF protection
- Input sanitization

### 📈 Scalability
- Modular architecture
- Separation of responsibilities
- Reusable code
- Optimized database
- Stateless REST API

## 🤝 Contributing

1. Fork the project
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is under the MIT License. See the `LICENSE` file for more details.

## 📞 Support

For technical support or inquiries:
- Email: support@construccionesgriegas.com
- Documentation: [Project Wiki]
- Issues: [GitHub Issues]

---

**Developed by:** Construcciones Griegas B&B S.A.  
**Version:** 1.0.0  
**Last updated:** August 2025
