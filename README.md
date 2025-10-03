# Tangle API

A comprehensive Laravel 12 API for a collaboration platform that connects creators and clients for project collaborations.

## Features

- **User Authentication & Authorization**
  - Sanctum-based API authentication
  - Social login (Google, Facebook)
  - Password reset functionality
  - Admin role management

- **Profile Management**
  - User profiles with portfolio images
  - Social media links
  - Profile completion tracking
  - Follow/unfollow system

- **Collaboration System**
  - Collaboration requests
  - Application system with Stripe payments
  - Review and rating system
  - Dispute resolution

- **Communication**
  - Real-time messaging with Pusher
  - User typing indicators
  - Message status tracking

- **Admin Features**
  - User management
  - Content moderation
  - Dispute resolution
  - Payment monitoring
  - Admin logs

- **Notifications**
  - Custom notification system
  - Notification preferences
  - Email notifications

- **Payment Integration**
  - Stripe payment processing
  - Webhook handling
  - Earnings dashboard

## Requirements

- PHP 8.2+
- Laravel 12
- MySQL 8.0+
- Composer
- Node.js & NPM

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd Tangle-api
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your .env file**
   ```env
   APP_NAME="Tangle API"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   APP_FRONTEND_URL=https://tangle-stage.vercel.app/
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=tangle_api
   DB_USERNAME=root
   DB_PASSWORD=your_password
   
   # Stripe Configuration
   STRIPE_KEY=your_stripe_public_key
   STRIPE_SECRET=your_stripe_secret_key
   STRIPE_WEBHOOK_SECRET=your_webhook_secret
   
   # Social Login
   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   
   FACEBOOK_CLIENT_ID=your_facebook_client_id
   FACEBOOK_CLIENT_SECRET=your_facebook_client_secret
   
   # Pusher for real-time features
   PUSHER_APP_ID=your_pusher_app_id
   PUSHER_APP_KEY=your_pusher_app_key
   PUSHER_APP_SECRET=your_pusher_app_secret
   ```

6. **Run database migrations**
   ```bash
   php artisan migrate
   ```

7. **Seed the database (optional)**
   ```bash
   php artisan db:seed
   ```

8. **Create storage link**
   ```bash
   php artisan storage:link
   ```

9. **Clear caches**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

10. **Start the development server**
    ```bash
    php artisan serve
    ```

## API Documentation

The API documentation is available at `/api/documentation` when running the application.

### Key Endpoints

#### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `POST /api/forgot-password` - Password reset request
- `POST /api/reset-password` - Password reset

#### Profile Management
- `GET /api/users/{id}` - Get user profile
- `POST /api/profile` - Update profile
- `GET /api/profile/completion` - Get profile completion status
- `POST /api/users/{id}/follow` - Follow user
- `DELETE /api/users/{id}/unfollow` - Unfollow user

#### Collaborations
- `GET /api/collaborations/{id}` - Get collaboration details
- `POST /api/collaborations` - Create/update collaboration
- `POST /api/collaborations/{id}/close` - Close collaboration
- `POST /api/collaborations/{id}/cancel` - Cancel collaboration

#### Applications
- `POST /api/applications/{collaboration}` - Apply for collaboration
- `PUT /api/applications/{application}/status` - Update application status
- `POST /api/applications/{application}/withdraw` - Withdraw application

#### Messaging
- `GET /api/messages` - Get messages
- `POST /api/messages` - Send message
- `PATCH /api/messages/{message}/status` - Update message status
- `POST /api/messages/typing` - Send typing indicator

#### Reviews
- `POST /api/collaborations/{collaboration}/reviews` - Create review
- `GET /api/users/{user}/reviews` - Get user reviews
- `POST /api/reviews/{review}/flag` - Flag review

#### Admin (Admin only)
- `GET /api/admin/dashboard` - Admin dashboard
- `GET /api/admin/users` - List users
- `POST /api/admin/users/{user}/status` - Update user status
- `GET /api/admin/disputes` - List disputes
- `POST /api/admin/disputes/{id}/resolve` - Resolve dispute

## Database Structure

The application includes the following main tables:

- `users` - User accounts and profiles
- `collaboration_requests` - Collaboration projects
- `applications` - User applications for collaborations
- `messages` - User messages
- `reviews` - User reviews and ratings
- `follows` - User follow relationships
- `disputes` - Dispute cases
- `reports` - Content reports
- `reminders` - System reminders
- `stripe_transactions` - Payment transactions
- `admin_logs` - Admin action logs

## File Structure

```
app/
├── Console/Commands/          # Artisan commands
├── Events/                    # Event classes
├── Http/
│   ├── Controllers/          # API controllers
│   ├── Middleware/           # Custom middleware
│   └── Requests/             # Form requests
├── Models/                   # Eloquent models
├── Notifications/            # Notification classes
├── Policies/                 # Authorization policies
└── Services/                 # Business logic services
```

## Key Features Implementation

### Image Processing
The application uses Intervention Image v3 for image processing:
- Profile photo resizing
- Portfolio image management
- Automatic image optimization

### Real-time Features
- Pusher integration for real-time messaging
- User typing indicators
- Live notifications

### Payment Processing
- Stripe integration for payments
- Webhook handling for payment events
- Earnings tracking

### Social Features
- Follow/unfollow system
- User reviews and ratings
- Social media integration

## Development

### Running Tests
```bash
php artisan test
```

### Code Style
```bash
./vendor/bin/pint
```

### Database Migrations
```bash
php artisan make:migration migration_name
php artisan migrate
```

### Creating Models
```bash
php artisan make:model ModelName -mcr
```

## Deployment

1. Set up your production environment
2. Configure your `.env` file with production values
3. Run `composer install --optimize-autoloader --no-dev`
4. Run `php artisan config:cache`
5. Run `php artisan route:cache`
6. Set up your web server (Apache/Nginx)
7. Configure SSL certificates
8. Set up database backups

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For support, please contact the development team or create an issue in the repository.
