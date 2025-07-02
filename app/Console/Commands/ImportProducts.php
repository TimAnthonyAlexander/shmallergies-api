<?php

namespace App\Console\Commands;

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import {file : Path to the JSON file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products, ingredients, and allergens from a JSON file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return Command::FAILURE;
        }

        $this->info("Reading JSON file: {$filePath}");

        try {
            $jsonContent = file_get_contents($filePath);
            $products = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON format: ' . json_last_error_msg());

                return Command::FAILURE;
            }

            if (! is_array($products)) {
                $this->error('JSON should contain an array of products');

                return Command::FAILURE;
            }

            $this->info('Found ' . count($products) . ' products to import');

            DB::beginTransaction();

            $productCount = 0;
            $ingredientCount = 0;
            $allergenCount = 0;

            foreach ($products as $productData) {
                if (! isset($productData['name']) || ! isset($productData['upc']) || ! isset($productData['ingredients'])) {
                    $this->warn('Skipping invalid product data (missing name, upc, or ingredients)');

                    continue;
                }

                // Check if product already exists by UPC
                $existingProduct = Product::where('upc_code', $productData['upc'])->first();
                if ($existingProduct) {
                    $this->warn("Product with UPC {$productData['upc']} already exists, skipping...");

                    continue;
                }

                // Create product
                $product = Product::create([
                    'name'     => $productData['name'],
                    'upc_code' => $productData['upc'],
                ]);

                $productCount++;
                $this->line("Created product: {$product->name}");

                // Create ingredients
                foreach ($productData['ingredients'] as $ingredientData) {
                    if (! isset($ingredientData['name'])) {
                        continue;
                    }

                    $ingredient = Ingredient::create([
                        'product_id' => $product->id,
                        'title'      => $ingredientData['name'],
                    ]);

                    $ingredientCount++;

                    // Create allergens for this ingredient
                    if (isset($ingredientData['allergens']) && is_array($ingredientData['allergens'])) {
                        foreach ($ingredientData['allergens'] as $allergenName) {
                            if (! empty($allergenName)) {
                                Allergen::create([
                                    'ingredient_id' => $ingredient->id,
                                    'name'          => $allergenName,
                                ]);

                                $allergenCount++;
                            }
                        }
                    }
                }
            }

            DB::commit();

            $this->newLine();
            $this->info('✅ Import completed successfully!');
            $this->line("Products created: {$productCount}");
            $this->line("Ingredients created: {$ingredientCount}");
            $this->line("Allergens created: {$allergenCount}");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Import failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
