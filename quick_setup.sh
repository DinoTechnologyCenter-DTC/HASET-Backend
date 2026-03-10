#!/bin/bash
# Quick Setup Script for Hostinger

echo "=== HASET Payment Backend Setup ==="
echo ""

# 1. Install dependencies
echo "1. Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# 2. Generate application key
echo "2. Generating application key..."
php artisan key:generate

# 3. Set permissions
echo "3. Setting permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# 4. Run migrations
echo "4. Running database migrations..."
php artisan migrate --force

# 5. Cache configuration
echo "5. Caching configuration..."
php artisan config:cache
php artisan route:cache

echo ""
echo "=== Setup Complete! ==="
echo ""
echo "Next steps:"
echo "1. Point your domain document root to: /public_html/paymnt/public"
echo "2. Test payment with: php test_payment.php"
echo ""
