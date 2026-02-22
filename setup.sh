#!/bin/bash

# HASET Payment Backend - Quick Setup Script
# This script sets up the Laravel backend for payment processing

set -e  # Exit on error

echo "🚀 HASET Payment Backend Setup"
echo "================================"
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from the Laravel project root."
    exit 1
fi

# Check PHP version
echo "📋 Checking PHP version..."
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "✅ PHP version: $PHP_VERSION"

# Check Composer
echo ""
echo "📋 Checking Composer..."
if ! command -v composer &> /dev/null; then
    echo "❌ Composer not found. Please install Composer first."
    exit 1
fi
COMPOSER_VERSION=$(composer -V | head -n1)
echo "✅ $COMPOSER_VERSION"

# Install dependencies
echo ""
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# Setup environment
echo ""
echo "🔧 Setting up environment..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "✅ Created .env file"
else
    echo "ℹ️  .env file already exists"
fi

# Generate application key
echo ""
echo "🔑 Generating application key..."
php artisan key:generate --ansi

# Run migrations
echo ""
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Clear caches
echo ""
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Display success message
echo ""
echo "✅ Setup completed successfully!"
echo ""
echo "📚 Next steps:"
echo "   1. Review and update .env file with your configuration"
echo "   2. Add payment gateway credentials to .env"
echo "   3. Start the server: php artisan serve --port=8001"
echo "   4. Test the API: curl -X POST http://127.0.0.1:8001/api/payment/initiate \\"
echo "      -H 'Content-Type: application/json' \\"
echo "      -d '{\"doctor_id\":\"doc_123\",\"amount\":10000,\"provider\":\"Mpesa\",\"payment_account\":\"+255712345678\"}'"
echo ""
echo "📖 Read PAYMENT_API_DOCUMENTATION.md for complete API documentation"
echo ""
