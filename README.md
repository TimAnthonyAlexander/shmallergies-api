# Shmallergies API

A Laravel-based REST API for allergen tracking and product safety management. This API powers the Shmallergies application, helping users identify allergens in food products through AI-powered ingredient analysis and community-driven data.

## Features

- **User Authentication** with email verification using Laravel Sanctum
- **Product Management** with image upload and AI-powered ingredient analysis
- **Allergen Detection** using GPT service for ingredient photo analysis
- **German Product Scraping** with automated data collection from OpenFoodFacts
- **German Language AI Processing** for ingredient analysis and allergen detection
- **Personal Allergy Profiles** for customized safety checks
- **Product Safety Verification** against user allergies
- **Comprehensive Search** by product name, UPC code, or allergens
- **Intelligent Scheduling** with adaptive scraping strategies
- **Community Database** - publicly accessible product information

## Technology Stack

- **Framework**: Laravel 11.x
- **Authentication**: Laravel Sanctum (API tokens)
- **Database**: MySQL/PostgreSQL
- **AI Service**: OpenAI GPT for ingredient analysis
- **Data Sources**: OpenFoodFacts API for German market
- **File Storage**: Laravel Storage (configurable)
- **Email**: Laravel Mail with verification
- **API Documentation**: Scribe
- **Scheduling**: Laravel Task Scheduler
- **HTTP Client**: Laravel HTTP Client for scraping

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

## AI-Powered Ingredient Analysis

The API uses OpenAI's GPT service to analyze ingredient photos and text:

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

### German Language Support

The GPT service includes specialized German language processing:

```php
// German ingredient analysis
$analysis = $gptService->analyzeGermanIngredients($germanIngredientsText);

// Returns German allergen names: weizen, milch, eier, erdnüsse, etc.
```

## 🇩🇪 German Product Scraping System

Automated scraping system that populates the database with thousands of German products from various sources, with AI-powered German ingredient analysis.

### Features

- **OpenFoodFacts Integration**: Access to 400,000+ German products
- **German Language Processing**: AI analysis optimized for German ingredients
- **Intelligent Scheduling**: Adaptive batch sizes based on database size
- **Category-Focused Scraping**: 10 major food categories
- **Rate-Limited**: Respectful API usage with built-in delays
- **Error Handling**: Comprehensive fallbacks and logging

### Quick Start

```bash
# Test the scraping system
php artisan scrape:test-german

# Demo with sample German products
php artisan scrape:demo

# Start scraping German products
php artisan scrape:german-products --limit=100 --category=beverages

# Enable scheduled scraping (runs daily at 2 AM)
php artisan scrape:german-scheduled
```

### Available Scraping Commands

| Command | Description | Example Usage |
|---------|-------------|---------------|
| `scrape:test-german` | Test all scraping components | `--test-api --show-categories` |
| `scrape:demo` | Demo with sample German products | `--clean` to remove demo data |
| `scrape:german-products` | Main scraping command | `--limit=100 --category=dairy --dry-run` |
| `scrape:german-scheduled` | Intelligent scheduled scraping | `--force` to override timing |

### Product Categories

- **beverages** (Getränke) - Soda, juice, water
- **dairy** (Milchprodukte) - Milk, cheese, yogurt
- **snacks** (Snacks) - Chips, crackers, bars
- **bakery** (Backwaren) - Cookies, pastries
- **confectionery** (Süßwaren) - Candy, chocolate
- **meat** (Fleisch) - Processed meats
- **fish** (Fisch) - Canned fish, seafood
- **fruits-and-vegetables** (Obst und Gemüse) - Canned/processed produce
- **frozen-foods** (Tiefkühlkost) - Frozen meals, ice cream
- **cereals-and-potatoes** (Getreide und Kartoffeln) - Bread, pasta, rice

### Scraping Strategies

The system automatically adapts its strategy based on database size:

- **Bootstrap Phase** (< 1,000 products): 50 per category, core categories
- **Growth Phase** (1,000-5,000 products): 30 per category, expanded categories  
- **Maintenance Phase** (> 5,000 products): 20 per category, high-turnover focus

### German Allergen Detection

AI system trained for German ingredient lists with proper allergen mapping:

| German | English | Examples |
|---------|---------|----------|
| `weizen` | Wheat/Gluten | Weizenmehl, Gluten |
| `milch` | Dairy/Milk | Vollmilch, Magermilchpulver |
| `eier` | Eggs | Eier, Eigelb |
| `erdnüsse` | Peanuts | Erdnüsse, Erdnussöl |
| `nüsse` | Tree nuts | Haselnüsse, Mandeln |
| `soja` | Soy | Soja, Sojalecithin |

### Configuration

```env
# Required for AI analysis
OPENAI_API_KEY=your_openai_api_key

# Optional: Custom user agent for scraping
OPENFOODFACTS_USER_AGENT="Shmallergies/1.0 (https://shmallergies.de)"
```

### Monitoring

- **Logs**: `storage/logs/german-scraping.log`
- **Statistics**: Products processed, created, updated, skipped, errors
- **Scheduling**: Automatic daily runs at 2 AM
- **Rate Limiting**: Built-in delays to respect API limits

For detailed scraping documentation, see [SCRAPING.md](SCRAPING.md).

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
- **Scraping Logs**: `storage/logs/german-scraping.log`
- **Run Tracking**: `storage/app/last_german_scraping.txt`

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
