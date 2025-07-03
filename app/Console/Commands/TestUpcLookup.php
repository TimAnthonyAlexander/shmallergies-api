<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\GermanProductScrapingService;
use App\Services\GPTService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestUpcLookup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:test-upc {upc : UPC code to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test UPC lookup against external services';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $upcCode = $this->argument('upc');
        $this->info("Testing UPC lookup for: {$upcCode}");

        // Check if product exists in database first
        $product = Product::where('upc_code', $upcCode)->first();
        if ($product) {
            $this->info("Product found in database:");
            $this->table(
                ['ID', 'Name', 'UPC Code'],
                [[$product->id, $product->name, $product->upc_code]]
            );
            return Command::SUCCESS;
        }

        $this->info("Product not found in database. Trying external sources...");

        // Create the scraping service
        $scrapingService = new GermanProductScrapingService();

        // Test OpenFoodFacts
        $this->comment("Checking OpenFoodFacts...");
        $productData = $scrapingService->searchOpenFoodFactsByUpc($upcCode);

        if ($productData) {
            $this->info("✅ Product found in OpenFoodFacts");
            $this->table(
                ['Name', 'UPC Code', 'Ingredients Length'],
                [[
                    $productData['name'] ?? 'N/A',
                    $productData['upc_code'] ?? 'N/A',
                    isset($productData['ingredients_text']) ? strlen($productData['ingredients_text']) : 0
                ]]
            );

            // Show a preview of ingredients text
            if (!empty($productData['ingredients_text'])) {
                $this->info("Ingredients Text Preview:");
                $this->line(substr($productData['ingredients_text'], 0, 200) . (strlen($productData['ingredients_text']) > 200 ? '...' : ''));
            }

            return Command::SUCCESS;
        } else {
            $this->warn("❌ Product not found in OpenFoodFacts");
        }

        // Test Rewe
        $this->comment("Checking Rewe...");
        $productData = $scrapingService->searchReweByUpc($upcCode);

        if ($productData) {
            $this->info("✅ Product found in Rewe");
            return Command::SUCCESS;
        } else {
            $this->warn("❌ Product not found in Rewe");
        }

        // Test Edeka
        $this->comment("Checking Edeka...");
        $productData = $scrapingService->searchEdekaByUpc($upcCode);

        if ($productData) {
            $this->info("✅ Product found in Edeka");
            return Command::SUCCESS;
        } else {
            $this->warn("❌ Product not found in Edeka");
        }

        $this->error("Product not found in any external source");
        return Command::FAILURE;
    }
}
