# PIT Facility Request System - Setup Guide

**Framework:** Laravel 12 | **Database:** MySQL | **Updated:** May 2026

---

## Requirements

- **PHP** 8.2+
- **Node.js** 14+
- **Composer** (PHP package manager)
- **MySQL** 5.7+
- **Administrator access** to install software

---

## Installation Steps

### 1. Install Required Software

**Windows:** Download [XAMPP](https://www.apachefriends.org) (includes PHP, MySQL, Apache)

**macOS:**
```bash
brew install php@8.2 mysql nodejs
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt install php8.2 php8.2-mysql mysql-server nodejs npm
```

Then install Composer from [getcomposer.org](https://getcomposer.org)

---

### 2. Setup Project

Copy project to your folder or use XAMPP: `C:\xampp\htdocs\pitfr`

```bash
cd pitfr
composer install
npm install
```

---

### 3. Configure Database

Create a database named `pitfr_db`:

```bash
mysql -u root -p
CREATE DATABASE pitfr_db;
EXIT;
```

Or use PhpMyAdmin at `http://localhost/phpmyadmin`

---

### 4. Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` file and update:
```env
DB_DATABASE=pitfr_db
DB_USERNAME=root
DB_PASSWORD=          # (leave empty if no password)

APP_ENV=local         # or 'production'
APP_DEBUG=true        # Set to false in production
APP_URL=http://localhost:8000
```

---

### 5. Initialize Database

```bash
php artisan migrate
npm run build
```

---

### 6. Run the Application

**Terminal 1:**
```bash
php artisan serve
```

**Terminal 2 (for real-time features):**
```bash
php artisan reverb:start
```

**Terminal 3 (optional, for development):**
```bash
npm run dev
```

Access application at: **http://localhost:8000**

---

## Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| `APP_KEY not set` | Run `php artisan key:generate` |
| Database connection error | Verify credentials in `.env` and MySQL is running |
| `npm: command not found` | Reinstall Node.js |
| Port 8000 already in use | Run `php artisan serve --port=8001` |
| Assets not loading (404) | Run `npm run build` |
| WebSocket errors | Make sure Reverb is running on Terminal 2 |

---

**Need help?** Check [Laravel Docs](https://laravel.com/docs) or [Laravel Reverb](https://laravel.com/docs/reverb)
