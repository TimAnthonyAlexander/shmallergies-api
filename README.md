<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Shmallergies API

A Laravel-based REST API for allergen tracking and product safety management. This API powers the Shmallergies application, helping users identify allergens in food products through AI-powered ingredient analysis and community-driven data.

## Features

- **User Authentication** with email verification using Laravel Sanctum
- **Product Management** with image upload and AI-powered ingredient analysis
- **Allergen Detection** using GPT service for ingredient photo analysis
- **Personal Allergy Profiles** for customized safety checks
- **Product Safety Verification** against user allergies
- **Comprehensive Search** by product name, UPC code, or allergens
- **Community Database** - publicly accessible product information

## Technology Stack

- **Framework**: Laravel 11.x
- **Authentication**: Laravel Sanctum (API tokens)
- **Database**: MySQL/PostgreSQL
- **AI Service**: OpenAI GPT for ingredient analysis
- **File Storage**: Laravel Storage (configurable)
- **Email**: Laravel Mail with verification
- **API Documentation**: Scribe

## Database Schema

### Core Models

- **User**: Authentication and profile management
- **Product**: Food products with UPC codes and ingredient images
- **Ingredient**: Individual ingredients extracted from products
- **Allergen**: Allergens associated with ingredients
- **UserAllergy**: User's personal allergy profile

### Relationships

```
User (1) -> (n) UserAllergy
Product (1) -> (n) Ingredient
Ingredient (1) -> (n) Allergen
```

## API Endpoints

### Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/auth/signup` | Register new user | No |
| POST | `/api/auth/login` | User login | No |
| POST | `/api/auth/logout` | User logout | Yes |
| GET | `/api/auth/email/verify/{id}/{hash}` | Verify email address | No |
| POST | `/api/auth/email/resend` | Resend verification email | No |

### User Profile

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/user` | Get user profile with allergies | Yes |
| GET | `/api/user/product-safety/{id}` | Check product safety for user | Yes |

### Products

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/products` | Get paginated products | No |
| GET | `/api/products/search` | Search products by name/UPC | No |
| GET | `/api/products/allergens` | Get products with specific allergens | No |
| GET | `/api/products/{id}` | Get product details | No |
| GET | `/api/products/upc/{upcCode}` | Get product by UPC code | No |
| POST | `/api/products` | Create new product with image | Yes |

### User Allergies

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/user/allergies` | Get user's allergies | Yes |
| POST | `/api/user/allergies` | Add new allergy | Yes |
| PUT | `/api/user/allergies/{id}` | Update allergy | Yes |
| DELETE | `/api/user/allergies/{id}` | Delete allergy | Yes |

### Health Check

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/ping` | API health check | No |

## Installation & Setup

### Prerequisites

- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- OpenAI API key (for ingredient analysis)

### Installation Steps

1. **Clone and setup**
   ```bash
   cd api
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Database Configuration**
   ```bash
   # Configure database in .env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=shmallergies
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # Run migrations
   php artisan migrate
   php artisan db:seed
   ```

3. **Storage Setup**
   ```bash
   php artisan storage:link
   ```

4. **Environment Configuration**
   ```bash
   # Add to .env
   OPENAI_API_KEY=your_openai_api_key
   
   # Email configuration for verification
   MAIL_MAILER=smtp
   MAIL_HOST=your_smtp_host
   MAIL_PORT=587
   MAIL_USERNAME=your_email
   MAIL_PASSWORD=your_password
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   ```

5. **Start Development Server**
   ```bash
   php artisan serve
   ```

## AI-Powered Ingredient Analysis

The API uses OpenAI's GPT service to analyze ingredient photos:

1. **Upload Process**: Users upload products with ingredient list photos
2. **AI Analysis**: GPT analyzes the image to extract ingredients and identify allergens
3. **Data Storage**: Parsed ingredients and allergens are stored in the database
4. **Safety Checks**: System cross-references with user allergy profiles

### GPT Service Integration

```php
// Example usage in ProductController
$gptService = new GPTService();
$analysis = $gptService->analyzeIngredientImage($imageBase64, $mimeType);

// Returns structured data:
[
    'ingredients' => [
        [
            'name' => 'High Fructose Corn Syrup',
            'allergens' => ['corn']
        ],
        // ...
    ]
]
```

## Authentication & Security

- **JWT Tokens**: Laravel Sanctum for API authentication
- **Email Verification**: Required for account activation
- **Rate Limiting**: Applied to sensitive endpoints
- **Input Validation**: Comprehensive request validation
- **CORS**: Configured for frontend integration

## File Storage

- **Ingredient Images**: Stored in `storage/app/public/ingredient-images/`
- **Public Access**: Available via `/storage/` URLs
- **Validation**: Max 2MB, JPEG/PNG/JPG/GIF formats
- **Cleanup**: Automatic cleanup on failed uploads

## API Documentation

The API includes comprehensive documentation generated with Scribe:

- **Interactive Docs**: Available at `/docs` when running
- **Endpoint Examples**: All endpoints include request/response examples
- **Authentication**: Documented authentication requirements
- **Error Handling**: Standard HTTP status codes and error messages

## Error Handling

The API uses standard HTTP status codes:

- **200**: Success
- **201**: Created
- **400**: Bad Request
- **401**: Unauthorized
- **403**: Forbidden (email not verified)
- **404**: Not Found
- **422**: Validation Error
- **500**: Server Error

Error responses include structured JSON:

```json
{
    "message": "Error description",
    "errors": {
        "field": ["Validation error details"]
    }
}
```

## Testing

Run the test suite:

```bash
php artisan test
```

## Console Commands

### Import Products
```bash
php artisan import:products {file}
```

### Test GPT Service
```bash
php artisan test:gpt-service
```

## Development & Debugging

### Debug Routes
```bash
# Check URL configuration
GET /api/debug/url-config

# Test email verification (development only)
GET /api/debug/send-verification/{userId}
```

### Logging
- Application logs: `storage/logs/laravel.log`
- GPT service interactions are logged for debugging
- Product creation failures are logged with context

## Production Deployment

1. **Environment**: Set `APP_ENV=production`
2. **Debug**: Set `APP_DEBUG=false`
3. **Remove Debug Routes**: Comment out debug routes in production
4. **Queue Configuration**: Configure queues for email processing
5. **Cache**: Enable Redis/Memcached for better performance
6. **HTTPS**: Ensure all endpoints use HTTPS in production

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow Laravel coding standards
4. Add tests for new functionality
5. Submit a pull request

## License

This project is open source and available under the MIT License.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.
