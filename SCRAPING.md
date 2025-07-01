# 🇩🇪 German Product Scraping System

A comprehensive scraping system for German food products with AI-powered ingredient analysis, designed to populate the Shmallergies database with thousands of German products.

## 🎯 **Overview**

The scraping system fetches German food products from multiple sources and uses AI to analyze German ingredient lists for allergen detection. It's optimized for the German market with German language processing and local product databases.

## 📦 **Data Sources**

### **1. OpenFoodFacts (Primary)**
- **Coverage**: 400,000+ German products
- **Quality**: Community-verified data
- **Languages**: German ingredient lists prioritized
- **Rate Limits**: Respectful API usage with delays
- **Categories**: 10 major food categories

### **2. German Supermarket APIs (Planned)**
- **REWE**: Placeholder for future implementation
- **Edeka**: Placeholder for future implementation

## 🚀 **Quick Start**

### **1. Test the System**
```bash
# Test all functionality
php artisan scrape:test-german

# Test specific components
php artisan scrape:test-german --test-api
php artisan scrape:test-german --test-gpt="Zucker, Weizenmehl, Milch, Eier"
php artisan scrape:test-german --show-categories
```

### **2. Run Manual Scraping**
```bash
# Basic scraping (100 products from beverages)
php artisan scrape:german-products --limit=100 --category=beverages

# Dry run (test without saving)
php artisan scrape:german-products --limit=50 --category=dairy --dry-run

# All categories, larger batch
php artisan scrape:german-products --limit=500
```

### **3. Scheduled Scraping**
Automatic scraping runs daily at 2 AM with adaptive batch sizes:
```bash
# Manual scheduled run
php artisan scrape:german-scheduled

# Force run (ignore time restrictions)
php artisan scrape:german-scheduled --force
```

## 📋 **Available Commands**

| Command | Description | Usage Example |
|---------|-------------|---------------|
| `scrape:german-products` | Main scraping command | `--limit=100 --category=dairy` |
| `scrape:german-scheduled` | Intelligent scheduled scraping | `--force` to override timing |
| `scrape:test-german` | Test all components | `--test-api` or `--show-categories` |

## 🏷️ **Product Categories**

| Category Key | German Name | Typical Products |
|-------------|-------------|------------------|
| `beverages` | Getränke | Soda, Juice, Water |
| `dairy` | Milchprodukte | Milk, Cheese, Yogurt |
| `snacks` | Snacks | Chips, Crackers, Bars |
| `cereals-and-potatoes` | Getreide und Kartoffeln | Bread, Pasta, Rice |
| `meat` | Fleisch | Processed meats |
| `fish` | Fisch | Canned fish, seafood |
| `fruits-and-vegetables` | Obst und Gemüse | Canned/processed produce |
| `frozen-foods` | Tiefkühlkost | Frozen meals, ice cream |
| `bakery` | Backwaren | Cookies, pastries |
| `confectionery` | Süßwaren | Candy, chocolate |

## 🤖 **AI Integration**

### **German Language Processing**
- **German Prompts**: AI receives instructions in German
- **German Allergens**: Returns German allergen names
- **Context-Aware**: Understands German food terminology

### **Allergen Mapping**
| German | English | Description |
|---------|---------|-------------|
| `weizen` | Wheat/Gluten | Wheat flour, gluten |
| `milch` | Dairy/Milk | Milk products |
| `eier` | Eggs | Egg products |
| `erdnüsse` | Peanuts | Peanut products |
| `nüsse` | Tree nuts | Almonds, hazelnuts, etc. |
| `soja` | Soy | Soy products |
| `fisch` | Fish | Fish products |
| `schalentiere` | Shellfish | Shrimp, crab, etc. |
| `sesam` | Sesame | Sesame seeds/oil |
| `mais` | Corn | Corn products |
| `sulfite` | Sulfites | Sulfur compounds |

## 📊 **Scraping Strategies**

The system adapts its scraping strategy based on database size:

### **Bootstrap Phase (< 1,000 products)**
- **Batch Size**: 50 products per category
- **Categories**: Core categories (beverages, dairy, snacks, bakery, etc.)
- **Goal**: Build initial diverse product database

### **Growth Phase (1,000 - 5,000 products)**
- **Batch Size**: 30 products per category  
- **Categories**: Expand to meat, fish, frozen foods
- **Goal**: Comprehensive category coverage

### **Maintenance Phase (> 5,000 products)**
- **Batch Size**: 20 products per category
- **Categories**: Focus on high-turnover categories
- **Goal**: Keep database current with new products

## ⚙️ **Configuration**

### **Environment Variables**
```env
# Required for AI analysis
OPENAI_API_KEY=your_openai_api_key

# Optional: Custom OpenFoodFacts user agent
OPENFOODFACTS_USER_AGENT="Shmallergies/1.0 (https://shmallergies.de)"
```

### **Rate Limiting**
- **OpenFoodFacts**: 100ms delay between requests
- **Categories**: 2 second delay between categories
- **Daily Limits**: Automatic scheduling prevents overuse

## 📈 **Monitoring & Logs**

### **Log Files**
- **Scheduled Scraping**: `storage/logs/german-scraping.log`
- **Laravel Logs**: `storage/logs/laravel.log`
- **Last Run Tracking**: `storage/app/last_german_scraping.txt`

### **Statistics Tracking**
Each scraping run provides:
- Products processed
- Products created
- Products updated
- Products skipped (duplicates)
- Errors encountered

## 🛡️ **Error Handling**

### **API Failures**
- Graceful fallbacks for network issues
- Retry logic for temporary failures
- Comprehensive error logging

### **AI Analysis Failures**
- Fallback to storing raw ingredient text
- Warning logs for manual review
- Continued processing of other products

### **Data Quality**
- German text detection heuristics
- UPC code validation and deduplication
- Product name cleaning and normalization

## 🔧 **Development & Testing**

### **Testing Commands**
```bash
# Test individual components
php artisan scrape:test-german --test-api
php artisan scrape:test-german --test-gpt="Wasser, Zucker, Zitronensäure"

# Dry run scraping
php artisan scrape:german-products --limit=10 --dry-run

# Test scheduling logic
php artisan scrape:german-scheduled --force
```

### **Database Seeding**
```bash
# Use existing JSON import for initial data
php artisan products:import presets/german-products.json

# Then run scraping to expand
php artisan scrape:german-products --limit=100
```

## 📚 **Usage Examples**

### **Bootstrap New Database**
```bash
# 1. Test system
php artisan scrape:test-german

# 2. Start with small batch
php artisan scrape:german-products --limit=50 --category=beverages

# 3. Expand to more categories
php artisan scrape:german-products --limit=200

# 4. Enable scheduled scraping
# (Automatic via cron: 0 2 * * * cd /path/to/api && php artisan schedule:run)
```

### **Targeted Category Scraping**
```bash
# Focus on specific category
php artisan scrape:german-products --limit=100 --category=dairy

# Multiple small batches
for category in beverages dairy snacks; do
  php artisan scrape:german-products --limit=30 --category=$category
  sleep 10
done
```

### **Production Monitoring**
```bash
# Check recent scraping results
tail -f storage/logs/german-scraping.log

# View database growth
php artisan tinker
>>> App\Models\Product::count()
>>> App\Models\Product::whereDate('created_at', today())->count()
```

## 🚨 **Important Notes**

1. **API Keys**: Ensure OPENAI_API_KEY is configured for AI analysis
2. **Rate Limits**: Respect API rate limits with built-in delays
3. **Disk Space**: Monitor storage for logs and cached data
4. **Database Size**: Watch database growth and optimize queries
5. **Scheduling**: Use Laravel's scheduler for automated runs

## 🆘 **Troubleshooting**

### **Common Issues**

**"No products returned from API"**
- Check internet connectivity
- Verify OpenFoodFacts API status
- Try different categories

**"GPT analysis failed"**
- Verify OPENAI_API_KEY in .env
- Check OpenAI account credits
- Test with simpler ingredient text

**"Database connection error"**  
- Ensure database is running
- Check database configuration
- Verify sufficient disk space

### **Debug Commands**
```bash
# Test specific ingredient analysis
php artisan scrape:test-german --test-gpt="Weizenmehl, Zucker, Milch"

# Check API connectivity
php artisan scrape:test-german --test-api

# View available categories
php artisan scrape:test-german --show-categories
``` 