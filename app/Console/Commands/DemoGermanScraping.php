<?php

namespace App\Console\Commands;

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\GPTService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoGermanScraping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:demo
                          {--clean : Clean demo products before running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate German scraping workflow with sample products';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🇩🇪 German Scraping Demo');
        $this->line('This demo shows the complete workflow with sample German products');
        $this->newLine();

        if ($this->option('clean')) {
            $this->cleanDemoProducts();
        }

        // Demo products with German ingredients
        $demoProducts = $this->getDemoProducts();

        $this->info("Processing " . count($demoProducts) . " demo products...");
        $this->newLine();

        $stats = ['created' => 0, 'processed' => 0, 'errors' => 0];

        foreach ($demoProducts as $productData) {
            $this->line("📦 Processing: {$productData['name']}");
            
            try {      
                $result = $this->processDemoProduct($productData);
                $stats[$result]++;
                $stats['processed']++;
                
                if ($result === 'created') {
                    $this->line("   ✅ Created with " . $productData['expected_ingredients'] . " ingredients");
                } else {
                    $this->line("   ⏭️  Skipped (already exists)");
                }
                
            } catch (\Exception $e) {
                $this->error("   ❌ Error: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->displayDemoResults($stats);
        $this->showDatabaseStatus();

        return Command::SUCCESS;
    }

    private function getDemoProducts(): array
    {
        return [
            [
                'name' => 'Coca-Cola Classic',
                'upc_code' => 'DEMO_4000177712',
                'ingredients_text' => 'Wasser, Zucker, Kohlensäure, Farbstoff E150d, Säuerungsmittel Phosphorsäure, natürliche Aromen, Koffein',
                'expected_ingredients' => 7,
                'category' => 'beverages'
            ],
            [
                'name' => 'Milka Alpenmilch Schokolade',
                'upc_code' => 'DEMO_7622210968',
                'ingredients_text' => 'Zucker, Kakaobutter, Magermilchpulver, Kakaomasse, Süßmolkenpulver, Butterreinfett, Haselnüsse, Emulgatoren (E322, E476), Aroma',
                'expected_ingredients' => 9,
                'category' => 'confectionery'
            ],
            [
                'name' => 'Knorr Tomaten Ketchup',
                'upc_code' => 'DEMO_8712100865',
                'ingredients_text' => 'Tomatenmark, Zucker, Branntweinessig, modifizierte Stärke, Salz, Gewürze, Süßungsmittel, Konservierungsstoff E211',  
                'expected_ingredients' => 8,
                'category' => 'sauces'
            ],
            [
                'name' => 'Bahlsen Leibniz Butterkeks',
                'upc_code' => 'DEMO_4017100141',
                'ingredients_text' => 'Weizenmehl, Zucker, Butter, Magermilchpulver, Kochsalz, Backtriebmittel, Emulgator Sojalecithin, Säuerungsmittel',
                'expected_ingredients' => 8,
                'category' => 'bakery'
            ],
            [
                'name' => 'Müller Milch Vanille',
                'upc_code' => 'DEMO_4025500005',
                'ingredients_text' => 'Vollmilch, Zucker, Magermilchpulver, Verdickungsmittel E1442, natürliches Vanillearoma, Stabilisator E407',
                'expected_ingredients' => 6,
                'category' => 'dairy'
            ]
        ];
    }

    private function processDemoProduct(array $productData): string
    {
        // Check if product exists
        $existingProduct = Product::where('upc_code', $productData['upc_code'])->first();
        if ($existingProduct) {
            return 'skipped';
        }

        DB::beginTransaction();

        try {
            // Create product
            $product = Product::create([
                'name' => $productData['name'],
                'upc_code' => $productData['upc_code'],
                'ingredient_image_path' => null,
            ]);

            // Analyze ingredients with GPT
            $this->line("   🤖 Analyzing German ingredients with AI...");
            $this->analyzeIngredientsWithGPT($product, $productData['ingredients_text']);

            DB::commit();
            return 'created';

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function analyzeIngredientsWithGPT(Product $product, string $ingredientsText): void
    {
        try {
            $gptService = new GPTService();
            $analysis = $gptService->analyzeGermanIngredients($ingredientsText);

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

            $this->line("      ✅ AI analysis complete: " . count($analysis['ingredients']) . " ingredients identified");

        } catch (\Exception $e) {
            $this->line("      ⚠️  AI analysis failed, using fallback method");
            
            // Fallback: simple parsing
            $ingredients = array_map('trim', explode(',', $ingredientsText));
            foreach ($ingredients as $ingredientName) {
                if (!empty($ingredientName)) {
                    Ingredient::create([
                        'product_id' => $product->id,
                        'title' => $ingredientName,
                    ]);
                }
            }
        }
    }

    private function cleanDemoProducts(): void
    {
        $this->warn('🧹 Cleaning demo products...');
        
        $demoProducts = Product::where('upc_code', 'LIKE', 'DEMO_%')->get();
        
        foreach ($demoProducts as $product) {
            // Delete related ingredients and allergens
            foreach ($product->ingredients as $ingredient) {
                $ingredient->allergens()->delete();
            }
            $product->ingredients()->delete();
            $product->delete();
        }

        $this->line("Removed " . $demoProducts->count() . " demo products");
        $this->newLine();
    }

    private function displayDemoResults(array $stats): void
    {
        $this->info('📊 Demo Results:');
        $this->line("Products processed: {$stats['processed']}");
        $this->line("Products created: {$stats['created']}");
        $this->line("Errors: {$stats['errors']}");
        $this->newLine();
    }

    private function showDatabaseStatus(): void
    {
        $totalProducts = Product::count();
        $totalIngredients = Ingredient::count();
        $totalAllergens = Allergen::count();
        $demoProducts = Product::where('upc_code', 'LIKE', 'DEMO_%')->count();

        $this->info('📈 Current Database Status:');
        $this->line("Total products: {$totalProducts}");
        $this->line("Total ingredients: {$totalIngredients}");
        $this->line("Total allergens: {$totalAllergens}");
        $this->line("Demo products: {$demoProducts}");
        $this->newLine();

        if ($demoProducts > 0) {
            $this->line('💡 To clean demo products: php artisan scrape:demo --clean');
        }

        $this->line('🚀 Ready for production scraping:');
        $this->line('   php artisan scrape:german-products --limit=50 --category=beverages');
    }
} 