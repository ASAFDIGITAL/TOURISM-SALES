#!/bin/bash

echo "🚀 Starting Hostinger Deployment..."

# Clear all caches
echo "📦 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run migrations
echo "🗄️  Running migrations..."
php artisan migrate --force

# Seed database (creates admin user)
echo "👤 Creating admin user..."
php artisan db:seed --force

# Create storage symlink
echo "🔗 Creating storage symlink..."
php artisan storage:link

# Set proper permissions for storage
echo "🔐 Setting storage permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Cache for performance
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Deployment complete!"
echo ""
echo "📧 Admin Login:"
echo "   Email: admin@admin.com"
echo "   Password: password"
echo ""
echo "⚠️  Remember to change the admin password after first login!"
