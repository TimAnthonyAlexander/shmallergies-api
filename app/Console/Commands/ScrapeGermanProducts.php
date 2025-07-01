<?php

namespace App\Console\Commands;

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\GPTService;
use App\Services\GermanProductScrapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScrapeGermanProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:german-products
                          {--limit=100 : Maximum number of products to scrape}
                          {--source=openfoodfacts : Data source (openfoodfacts, rewe, edeka)}
                          {--category= : Product category to focus on}
                          {--dry-run : Run without saving to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape German food products from various sources and analyze ingredients with AI';

    private GermanProductScrapingService $scrapingService;
    private GPTService $gptService;

    public function __construct()
    {
        parent::__construct();
        $this->scrapingService = new GermanProductScrapingService();
        $this->gptService = new GPTService();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $source = $this->option('source');
        $category = $this->option('category');
        $dryRun = $this->option('dry-run');

        $this->info("🇩🇪 Starting German product scraping...");
        $this->line("Source: {$source}");
        $this->line("Limit: {$limit} products");
        $this->line("Category: " . ($category ?: 'All categories'));
        $this->line("Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE'));

        try {
            $products = $this->scrapeProducts($source, $limit, $category);
            
            if (empty($products)) {
                $this->warn('No products found to process.');
                return Command::SUCCESS;
            }

            $this->info("Found " . count($products) . " products to process");

            $stats = [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0
            ];

            $progressBar = $this->output->createProgressBar(count($products));
            $progressBar->start();

            foreach ($products as $productData) {
                try {
                    $result = $this->processProduct($productData, $dryRun);
                    $stats[$result]++;
                    $stats['processed']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Failed to process product', [
                        'product' => $productData['name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ]);
                }
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->displayResults($stats, $dryRun);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Scraping failed: " . $e->getMessage());
            Log::error('German product scraping failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    private function scrapeProducts(string $source, int $limit, ?string $category): array
    {
        $this->line("Fetching products from {$source}...");

        switch ($source) {
            case 'openfoodfacts':
                return $this->scrapingService->scrapeOpenFoodFacts($limit, $category);
            
            case 'rewe':
                return $this->scrapingService->scrapeRewe($limit, $category);
            
            case 'edeka':
                return $this->scrapingService->scrapeEdeka($limit, $category);
            
            default:
                throw new \InvalidArgumentException("Unknown source: {$source}");
        }
    }

    private function processProduct(array $productData, bool $dryRun): string
    {
        // Check if product already exists
        $existingProduct = Product::where('upc_code', $productData['upc_code'])->first();
        
        if ($existingProduct) {
            // Update existing product if ingredients are missing
            if ($existingProduct->ingredients->isEmpty() && !empty($productData['ingredients_text'])) {
                if (!$dryRun) {
                    $this->analyzeAndStoreIngredients($existingProduct, $productData['ingredients_text']);
                }
                return 'updated';
            }
            return 'skipped';
        }

        if ($dryRun) {
            return 'created'; // Would be created
        }

        DB::beginTransaction();

        try {
            // Create new product
            $product = Product::create([
                'name' => $productData['name'],
                'upc_code' => $productData['upc_code'],
                'ingredient_image_path' => null, // No image for scraped products initially
            ]);

            // Analyze ingredients with AI if available
            if (!empty($productData['ingredients_text'])) {
                $this->analyzeAndStoreIngredients($product, $productData['ingredients_text']);
            }

            DB::commit();
            return 'created';

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function analyzeAndStoreIngredients(Product $product, string $ingredientsText): void
    {
        try {
            // Use GPT to analyze German ingredients text
            $analysis = $this->gptService->analyzeGermanIngredients($ingredientsText);

            foreach ($analysis['ingredients'] as $ingredientData) {
                $ingredient = Ingredient::create([
                    'product_id' => $product->id,
                    'title' => $ingredientData['name'],
                ]);

                // Store allergens
                if (!empty($ingredientData['allergens'])) {
                    foreach ($ingredientData['allergens'] as $allergenName) {
                        Allergen::create([
                            'ingredient_id' => $ingredient->id,
                            'name' => $allergenName,
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to analyze ingredients with AI', [
                'product_id' => $product->id,
                'ingredients_text' => $ingredientsText,
                'error' => $e->getMessage()
            ]);
            
            // Fallback: create a single ingredient with the full text
            Ingredient::create([
                'product_id' => $product->id,
                'title' => $ingredientsText,
            ]);
        }
    }

    private function displayResults(array $stats, bool $dryRun): void
    {
        $this->info('✅ Scraping completed!');
        $this->newLine();
        
        $this->line("📊 Results Summary:");
        $this->line("Processed: {$stats['processed']}");
        $this->line("Created: {$stats['created']}");
        $this->line("Updated: {$stats['updated']}");
        $this->line("Skipped: {$stats['skipped']}");
        $this->line("Errors: {$stats['errors']}");

        if ($dryRun) {
            $this->warn('This was a DRY RUN - no data was actually saved to the database.');
        }
    }
} 