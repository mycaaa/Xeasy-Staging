`# 🚀 Getting Started with Xeasy Laravel

This guide will help you set up and run Xeasy Laravel on your local development environment.

## 📋 Prerequisites

Before you begin, ensure you have the following installed:

- **PHP 8.2 or higher**
- **Composer 2.0 or higher**
- **Node.js 18+ and npm**
- **MySQL 8.0 or MariaDB 10.5+**
- **Git** (for version control)

## 🛠️ Installation Steps

### Step 1: Clone the Repository

```bash
git clone https://github.com/devvspl/Xeasy_laravel.git
cd Xeasy_laravel
```

### Step 2: Install Dependencies

Install PHP dependencies:
```bash
composer install
```

Install Node.js dependencies:
```bash
npm install
```

### Step 3: Environment Configuration

Copy the environment file:
```bash
cp .env.example .env
```

Generate application key:
```bash
php artisan key:generate
```

### Step 4: Database Configuration

Edit your `.env` file with your database settings:

```env
# Application
APP_NAME=Xeasy
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Primary Database (Expense Management)
DB_CONNECTION=expense
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=expense
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Secondary Database (HRIMS - Employee Management)
SECOND_DB_CONNECTION=hrims
SECOND_DB_HOST=127.0.0.1
SECOND_DB_PORT=3306
SECOND_DB_DATABASE=hrims
SECOND_DB_USERNAME=your_username
SECOND_DB_PASSWORD=your_password

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database

# Queue
QUEUE_CONNECTION=database
```

### Step 5: Database Setup

Create the required databases:
```sql
CREATE DATABASE expense;
CREATE DATABASE hrims;
```

Run migrations:
```bash
php artisan migrate
```

Seed the database with initial data:
```bash
php artisan db:seed
```

### Step 6: Asset Compilation

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

### Step 7: Storage Setup

Create the symbolic link for file storage:
```bash
php artisan storage:link
```

### Step 8: Start the Application

Start the development server:
```bash
php artisan serve
```

Visit `http://localhost:8000` to access the application.

## 🔐 Default Login Credentials

After seeding, you can log in with:

- **Employee ID**: `admin`
- **Password**: `admin123`
- **Company**: VNR Seeds Pvt Ltd (1)
- **Financial Year**: Current year

## 🏗️ Multi-Company Setup

Xeasy Laravel supports multiple companies with separate databases. The system is pre-configured for:

1. **VNR Seeds Pvt Ltd** (Company ID: 1)
2. **VNR Group Concern** (Company ID: 2)
3. **Nursery Pvt Ltd** (Company ID: 3)
4. **VVNR** (Company ID: 4)

Each company has separate:
- HRIMS database (employee data)
- Expense database (expense claims)

Configuration is in `config/company_databases.php`.

## 📁 Directory Structure

```
Xeasy_laravel/
├── app/
│   ├── Http/Controllers/
│   │   ├── admin/          # Admin controllers
│   │   ├── Api/            # API controllers
│   │   ├── AuthController.php
│   │   └── HomeController.php
│   ├── Models/             # Eloquent models
│   ├── Helpers/            # Helper functions
│   └── ...
├── config/
│   ├── company_databases.php  # Multi-company config
│   ├── database.php           # Database connections
│   └── ...
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── docs/                  # This documentation
├── resources/
│   ├── views/             # Blade templates
│   └── js/                # Frontend JavaScript
├── routes/
│   ├── web.php            # Web routes
│   ├── api.php            # API routes
│   └── backend.php        # Backend routes
└── ...
```

## 🔧 Configuration

### Financial Year Setup

1. Navigate to **Settings > Financial Year**
2. Create or activate the current financial year
3. Set start and end dates
4. Mark as active

### User Management

1. Go to **Admin > Users**
2. Create users and assign roles
3. Set permissions per user
4. Link users to employees

### Permission Setup

1. Access **Admin > Permissions**
2. Create permission groups
3. Assign permissions to roles
4. Configure menu access

## 🚦 Development Workflow

### Running in Development Mode

Start all development services:
```bash
composer run dev
```

This starts:
- Laravel development server
- Queue worker
- Log monitoring (Pail)
- Vite asset compilation

### Testing

Run the test suite:
```bash
composer test
```

### Code Style

Fix code style issues:
```bash
./vendor/bin/pint
```

## 🔍 Troubleshooting

### Common Issues

**Database Connection Error**
- Verify database credentials in `.env`
- Ensure MySQL/MariaDB is running
- Check database exists

**Permission Denied Errors**
- Set proper file permissions: `chmod -R 755 storage bootstrap/cache`
- Ensure web server can write to storage directories

**Asset Compilation Issues**
- Clear npm cache: `npm cache clean --force`
- Delete `node_modules` and reinstall: `rm -rf node_modules && npm install`

**Session Issues**
- Clear application cache: `php artisan cache:clear`
- Clear config cache: `php artisan config:clear`
- Clear route cache: `php artisan route:clear`

### Debug Mode

Enable debug mode in `.env`:
```env
APP_DEBUG=true
```

Use Laravel Debugbar for detailed debugging information.

## 📚 Next Steps

After successful installation:

1. **Explore the Dashboard**: [HomeController Documentation](controllers/HomeController.md)
2. **Understand Authentication**: [Authentication System](authentication.md)
3. **Learn the Architecture**: [Architecture Overview](architecture.md)
4. **API Integration**: [API Documentation](api/expense-claims.md)

## 🆘 Getting Help

- **Documentation**: Browse the [full documentation](README.md)
- **Controller Reference**: Check specific [controller documentation](controllers/)
- **API Reference**: Review [API endpoints](api/)
- **Database Schema**: Understand the [database structure](database/schema.md)

---

**Ready to start building with Xeasy Laravel! 🎉**