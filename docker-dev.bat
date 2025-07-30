@echo off
echo 🚀 Starting Jewelry SaaS Platform Development Environment...

REM Check if Docker is running
docker info >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker is not running. Please start Docker first.
    exit /b 1
)

REM Create .env file if it doesn't exist
if not exist .env (
    echo 📝 Creating .env file from .env.example...
    copy .env.example .env
)

REM Build and start containers
echo 🐳 Building and starting Docker containers...
docker-compose up -d --build

REM Wait for MySQL to be ready
echo ⏳ Waiting for MySQL to be ready...
:wait_mysql
docker-compose exec mysql mysqladmin ping -h"localhost" --silent >nul 2>&1
if errorlevel 1 (
    echo Waiting for MySQL...
    timeout /t 2 /nobreak >nul
    goto wait_mysql
)

REM Install Composer dependencies
echo 📦 Installing Composer dependencies...
docker-compose exec app composer install

REM Generate application key
echo 🔑 Generating application key...
docker-compose exec app php artisan key:generate

REM Install NPM dependencies
echo 📦 Installing NPM dependencies...
docker-compose exec vite npm install

REM Run database migrations
echo 🗄️ Running database migrations...
docker-compose exec app php artisan migrate

echo ✅ Development environment is ready!
echo.
echo 🌐 Application: http://localhost
echo 🔧 Vite Dev Server: http://localhost:5173
echo 🗄️ MySQL: localhost:3306
echo 🔴 Redis: localhost:6379
echo.
echo 📝 Useful commands:
echo   docker-compose logs -f        # View logs
echo   docker-compose exec app bash  # Access app container
echo   docker-compose down           # Stop containers