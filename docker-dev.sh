#!/bin/bash

# Jewelry SaaS Platform - Development Setup Script

echo "🚀 Starting Jewelry SaaS Platform Development Environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example..."
    cp .env.example .env
fi

# Build and start containers
echo "🐳 Building and starting Docker containers..."
docker-compose up -d --build

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
until docker-compose exec mysql mysqladmin ping -h"localhost" --silent; do
    echo "Waiting for MySQL..."
    sleep 2
done

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
docker-compose exec app composer install

# Generate application key
echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate

# Install NPM dependencies
echo "📦 Installing NPM dependencies..."
docker-compose exec vite npm install

# Run database migrations
echo "🗄️ Running database migrations..."
docker-compose exec app php artisan migrate

echo "✅ Development environment is ready!"
echo ""
echo "🌐 Application: http://localhost"
echo "🔧 Vite Dev Server: http://localhost:5173"
echo "🗄️ MySQL: localhost:3306"
echo "🔴 Redis: localhost:6379"
echo ""
echo "📝 Useful commands:"
echo "  docker-compose logs -f        # View logs"
echo "  docker-compose exec app bash  # Access app container"
echo "  docker-compose down           # Stop containers"