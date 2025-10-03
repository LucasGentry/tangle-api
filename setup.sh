#!/bin/bash

echo "🚀 Setting up Tangle API..."

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    echo "✅ .env file created"
else
    echo "✅ .env file already exists"
fi

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install

# Install Node.js dependencies
echo "📦 Installing Node.js dependencies..."
npm install

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Regenerate autoload files
echo "🔄 Regenerating autoload files..."
composer dump-autoload

echo "✅ Setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure your .env file with database credentials"
echo "2. Run: php artisan migrate"
echo "3. Run: php artisan serve"
echo "4. Visit: http://localhost:8000/api/documentation" 