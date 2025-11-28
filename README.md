# Tours and Travels Backend - PHP/MySQL

This is a PHP backend application for the Tours and Travels platform, built with MySQL database.

## Features

- RESTful API endpoints
- JWT-based authentication
- MySQL database with PDO
- User management
- Package management
- Booking system
- Review system
- Wishlist functionality
- Newsletter subscription
- Contact lead management

## Requirements

- PHP >= 7.4
- MySQL >= 5.7 or MariaDB >= 10.2
- Composer
- Apache/Nginx with mod_rewrite enabled

## Installation

1. **Clone or navigate to the project directory:**
   ```bash
   cd php-backend
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up environment variables:**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` file with your database credentials and other configuration.

4. **Create the database:**
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   Or import the `database/schema.sql` file using phpMyAdmin or your preferred MySQL client.

5. **Configure your web server:**
   
   **For Apache:**
   - Ensure mod_rewrite is enabled
   - Point DocumentRoot to the `php-backend` directory
   - The `.htaccess` file should handle URL rewriting
   
   **For Nginx:**
   ```nginx
   server {
       listen 80;
       server_name localhost;
       root /path/to/php-backend;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
           fastcgi_index index.php;
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
   }
   ```

## Environment Variables

Edit the `.env` file with your configuration:

```env
# Server Configuration
APP_ENV=development
PORT=5000
CLIENT_URL=http://localhost:3000

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tour_travels
DB_USER=root
DB_PASS=

# JWT Configuration
JWT_SECRET=your-secret-key-change-this-in-production
JWT_REFRESH_SECRET=your-refresh-secret-key-change-this-in-production
JWT_EXPIRY=24h
JWT_REFRESH_EXPIRY=30d

# Email Configuration (optional)
EMAIL_FROM=no-reply@travelapp.com
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USER=
EMAIL_PASS=

# Payment Gateway Configuration (optional)
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register a new user
- `POST /api/auth/login` - Login user
- `POST /api/auth/refresh` - Refresh access token
- `POST /api/auth/logout` - Logout user

### Users
- `GET /api/users/profile` - Get user profile (authenticated)
- `PUT /api/users/profile` - Update user profile (authenticated)

### Packages
- `GET /api/packages` - List packages (with filters)
- `GET /api/packages/{id}` - Get package details
- `POST /api/packages` - Create package (admin only)
- `PUT /api/packages/{id}` - Update package (admin only)
- `DELETE /api/packages/{id}` - Delete package (admin only)

### Bookings
- `POST /api/bookings` - Create booking (authenticated)
- `GET /api/bookings` - Get user bookings (authenticated)
- `GET /api/bookings/{id}` - Get booking details (authenticated)

### Reviews
- `POST /api/reviews` - Create review (authenticated)
- `GET /api/reviews?packageId={id}` - Get package reviews

### Wishlist
- `POST /api/wishlist` - Add to wishlist (authenticated)
- `GET /api/wishlist` - Get user wishlist (authenticated)
- `DELETE /api/wishlist/{id}` - Remove from wishlist (authenticated)

### Newsletter
- `POST /api/newsletter` - Subscribe to newsletter

### Contact Leads
- `POST /api/leads` - Create contact lead

## Health Check

- `GET /health` - Health check endpoint

## Development

The application uses:
- **PDO** for database operations
- **Firebase JWT** for token management
- **vlucas/phpdotenv** for environment variable management

## Database Schema

The database schema is defined in `database/schema.sql`. It includes tables for:
- Users
- Refresh Tokens
- Packages
- Bookings
- Reviews
- Payments
- Wishlist
- Newsletter
- Contact Leads
- Quotes

## Security Notes

- Passwords are hashed using `password_hash()` with bcrypt
- JWT tokens are used for authentication
- Refresh tokens are stored hashed in the database
- CORS is configured for the client URL
- SQL injection protection via PDO prepared statements

## License

ISC

