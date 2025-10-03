@echo off
echo 🚀 Setting up Tangle API...

REM Check if .env exists
if not exist .env (
    echo 📝 Creating .env file...
    copy .env.example .env
    echo ✅ .env file created
) else (
    echo ✅ .env file already exists
)

REM Install PHP dependencies
echo 📦 Installing PHP dependencies...
composer install

REM Install Node.js dependencies
echo 📦 Installing Node.js dependencies...
npm install

REM Generate application key
echo 🔑 Generating application key...
php artisan key:generate

REM Create storage link
echo 🔗 Creating storage link...
php artisan storage:link

REM Clear caches
echo 🧹 Clearing caches...
php artisan config:clear
php artisan cache:clear
php artisan route:clear

REM Regenerate autoload files
echo 🔄 Regenerating autoload files...
composer dump-autoload

echo ✅ Setup complete!
echo.
echo Next steps:
echo 1. Configure your .env file with database credentials
echo 2. Run: php artisan migrate
echo 3. Run: php artisan serve
echo 4. Visit: http://localhost:8000/api/documentation
pause 