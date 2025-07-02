<?php

namespace App\Console\Commands;

use App\Services\GermanProductScrapingService;
use App\Services\GPTService;
use Illuminate\Console\Command;

class TestGermanScraping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:test-german
                          {--show-categories : Show available categories}
                          {--test-api : Test API connectivity}
                          {--test-gpt= : Test GPT analysis with sample German text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test German product scraping functionality and show available options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🇩🇪 Testing German Product Scraping System');
        $this->line('=' . str_repeat('=', 50));

        if ($this->option('show-categories')) {
            $this->showCategories();
            return Command::SUCCESS;
        }

        if ($this->option('test-api')) {
            return $this->testApiConnectivity();
        }

        if ($gptText = $this->option('test-gpt')) {
            return $this->testGptAnalysis($gptText);
        }

        // Run all tests
        $this->testApiConnectivity();
        $this->newLine();
        $this->testSampleScraping();
        $this->newLine();
        $this->testGptAnalysis();

        return Command::SUCCESS;
    }

    private function showCategories(): void
    {
        $scrapingService = new GermanProductScrapingService();
        $categories = $scrapingService->getAvailableCategories();

        $this->info('📦 Available Product Categories:');
        $this->newLine();

        foreach ($categories as $key => $germanName) {
            $this->line("  {$key} → {$germanName}");
        }

        $this->newLine();
        $this->line('Usage examples:');
        $this->line('  php artisan scrape:german-products --category=beverages --limit=10');
        $this->line('  php artisan scrape:german-products --category=dairy --limit=20 --dry-run');
    }

    private function testApiConnectivity(): int
    {
        $this->info('🔗 Testing OpenFoodFacts API connectivity...');

        try {
            $scrapingService = new GermanProductScrapingService();
            
            // Test with a very small limit
            $products = $scrapingService->scrapeOpenFoodFacts(2, 'beverages');

            if (empty($products)) {
                $this->warn('⚠️  No products returned from API');
                return Command::FAILURE;
            }

            $this->line("✅ API connection successful! Found " . count($products) . " sample products");
            
            // Show sample product
            if (!empty($products[0])) {
                $sample = $products[0];
                $this->line("\n📦 Sample Product:");
                $this->line("  Name: " . $sample['name']);
                $this->line("  UPC: " . $sample['upc_code']);
                $this->line("  Source: " . $sample['source']);
                $this->line("  Ingredients: " . substr($sample['ingredients_text'], 0, 100) . '...');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ API connectivity test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function testSampleScraping(): void
    {
        $this->info('🧪 Testing sample scraping (dry run)...');

        try {
            // Run a small test scraping
            $exitCode = $this->call('scrape:german-products', [
                '--limit' => 3,
                '--category' => 'beverages', 
                '--dry-run' => true,
                '--source' => 'openfoodfacts'
            ]);

            if ($exitCode === 0) {
                $this->line('✅ Sample scraping test successful');
            } else {
                $this->error('❌ Sample scraping test failed');
            }

        } catch (\Exception $e) {
            $this->error('❌ Sample scraping error: ' . $e->getMessage());
        }
    }

    private function testGptAnalysis(?string $testText = null): int
    {
        $this->info('🤖 Testing GPT German ingredient analysis...');

        // Use provided text or default sample
        $sampleText = $testText ?: 'Wasser, Zucker, Kohlensäure, Zitronensäure, natürliche Aromen, Koffein, Karamellfarbe E150d, Phosphorsäure';

        $this->line("Testing with: {$sampleText}");

        try {
            $gptService = new GPTService();
            $analysis = $gptService->analyzeGermanIngredients($sampleText);

            $this->line('✅ GPT analysis successful!');
            $this->newLine();
            
            $this->line('📋 Analysis Results:');
            foreach ($analysis['ingredients'] as $ingredient) {
                $allergens = empty($ingredient['allergens']) 
                    ? 'No allergens' 
                    : implode(', ', $ingredient['allergens']);
                    
                $this->line("  • {$ingredient['name']} → {$allergens}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ GPT analysis failed: ' . $e->getMessage());
            
            // Check if it's an API key issue
            if (strpos($e->getMessage(), 'API key') !== false) {
                $this->warn('💡 Make sure OPENAI_API_KEY is set in your .env file');
            }
            
            return Command::FAILURE;
        }
    }
} 