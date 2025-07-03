<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GermanProductScrapingService
{
    private const OPENFOODFACTS_API_URL = 'https://world.openfoodfacts.org/api/v2';
    
    /**
     * Scrape products from OpenFoodFacts focusing on German market
     * 
     * @return array<int, array<string, mixed>>
     */
    public function scrapeOpenFoodFacts(int $limit = 100, ?string $category = null): array
    {
        $products = [];
        $page = 1;
        $pageSize = min(50, $limit); // OpenFoodFacts API limit
        
        while (count($products) < $limit) {
            $searchParams = [
                'countries' => 'germany', // Focus on German products
                'fields' => 'code,product_name,ingredients_text,allergens,categories,brands,image_ingredients_url',
                'page_size' => $pageSize,
                'page' => $page,
                'json' => 1
            ];

            // Add category filter if specified
            if ($category) {
                $searchParams['categories'] = $category;
            }

            // Filter for products with ingredient lists
            $searchParams['ingredients_text'] = '!=""';

            try {
                $response = Http::timeout(30)
                    ->get(self::OPENFOODFACTS_API_URL . '/search', $searchParams);

                if (!$response->successful()) {
                    Log::warning('OpenFoodFacts API request failed', [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    break;
                }

                $data = $response->json();
                
                if (empty($data['products'])) {
                    Log::info('No more products found on page ' . $page);
                    break;
                }

                foreach ($data['products'] as $product) {
                    if (count($products) >= $limit) {
                        break;
                    }

                    $processedProduct = $this->processOpenFoodFactsProduct($product);
                    if ($processedProduct) {
                        $products[] = $processedProduct;
                    }
                }

                $page++;
                
                // Respect API rate limits
                usleep(100000); // 100ms delay

            } catch (\Exception $e) {
                Log::error('Error scraping OpenFoodFacts', [
                    'page' => $page,
                    'error' => $e->getMessage()
                ]);
                break;
            }
        }

        Log::info('OpenFoodFacts scraping completed', [
            'products_found' => count($products),
            'pages_processed' => $page - 1
        ]);

        return array_slice($products, 0, $limit);
    }

    /**
     * Process and validate OpenFoodFacts product data
     * 
     * @param array<string, mixed> $product
     * @return array<string, mixed>|null
     */
    private function processOpenFoodFactsProduct(array $product): ?array
    {
        // Validate required fields
        if (empty($product['code']) || empty($product['product_name'])) {
            Log::debug('OpenFoodFacts product rejected: missing code or product_name', [
                'has_code' => !empty($product['code']),
                'has_name' => !empty($product['product_name'])
            ]);
            return null;
        }

        // Skip products without ingredient lists
        if (empty($product['ingredients_text'])) {
            Log::debug('OpenFoodFacts product rejected: missing ingredients_text');
            return null;
        }

        // Prioritize German ingredient lists
        $ingredientsText = $this->extractGermanIngredients($product);
        if (!$ingredientsText) {
            Log::debug('OpenFoodFacts product rejected: no German ingredients detected', [
                'has_ingredients_text_de' => isset($product['ingredients_text_de']),
                'ingredients_text_sample' => substr($product['ingredients_text'], 0, 100)
            ]);
            
            // Temporary modification to make it work even without German ingredients
            // Just use the available ingredients text if German text not found
            Log::debug('Bypassing German language requirement for testing');
            $ingredientsText = $product['ingredients_text'];
        }

        return [
            'name' => $this->cleanProductName($product['product_name']),
            'upc_code' => $product['code'],
            'ingredients_text' => $ingredientsText,
            'categories' => $product['categories'] ?? null,
            'brands' => $product['brands'] ?? null,
            'allergens' => $product['allergens'] ?? null,
            'source' => 'openfoodfacts',
            'image_ingredients_url' => $product['image_ingredients_url'] ?? null,
        ];
    }

    /**
     * Extract German ingredients text from multilingual product data
     * 
     * @param array<string, mixed> $product
     */
    private function extractGermanIngredients(array $product): ?string
    {
        // Try to get German-specific ingredients first
        if (isset($product['ingredients_text_de']) && !empty($product['ingredients_text_de'])) {
            Log::debug('Found German ingredients text');
            return $product['ingredients_text_de'];
        }

        // Fall back to general ingredients text if it looks German
        $ingredientsText = $product['ingredients_text'] ?? '';
        
        if (empty($ingredientsText)) {
            Log::debug('No ingredients text found');
            return null;
        }

        // Simple heuristic to detect German text
        if ($this->looksLikeGerman($ingredientsText)) {
            Log::debug('General ingredients text appears to be German');
            return $ingredientsText;
        }

        Log::debug('Ingredients text does not appear to be German', [
            'text_sample' => substr($ingredientsText, 0, 100)
        ]);
        return null;
    }

    /**
     * Simple heuristic to detect German text in ingredients
     */
    private function looksLikeGerman(string $text): bool
    {
        $germanWords = [
            'zucker', 'wasser', 'salz', 'öl', 'mehl', 'butter', 'milch', 'eier',
            'weizenmehl', 'vollmilch', 'palmöl', 'sonnenblumenöl', 'glukose',
            'fruktose', 'maltodextrin', 'lecithin', 'vanillin', 'aroma',
            'zitronensäure', 'ascorbinsäure', 'natriumchlorid', 'kalzium',
            'vitamin', 'konservierungsstoff', 'farbstoff', 'emulgator',
            'stabilisator', 'antioxidationsmittel', 'säureregulator'
        ];

        $text = strtolower($text);
        $matches = 0;

        foreach ($germanWords as $word) {
            if (strpos($text, $word) !== false) {
                $matches++;
            }
        }

        // Consider it German if we find at least 2 German food-related words
        return $matches >= 2;
    }

    /**
     * Clean and normalize product names
     */
    private function cleanProductName(string $name): string
    {
        // Remove extra whitespace and special characters
        $name = preg_replace('/\s+/', ' ', trim($name));
        
        // Limit length
        if (strlen($name) > 255) {
            $name = substr($name, 0, 252) . '...';
        }

        return $name;
    }

    /**
     * Placeholder for REWE scraping (to be implemented)
     * 
     * @return array<int, array<string, mixed>>
     */
    public function scrapeRewe(int $limit = 100, ?string $category = null): array
    {
        // TODO: Implement REWE scraping
        Log::info('REWE scraping not yet implemented');
        return [];
    }

    /**
     * Placeholder for Edeka scraping (to be implemented)
     * 
     * @return array<int, array<string, mixed>>
     */
    public function scrapeEdeka(int $limit = 100, ?string $category = null): array
    {
        // TODO: Implement Edeka scraping
        Log::info('Edeka scraping not yet implemented');
        return [];
    }

    /**
     * Get available categories for OpenFoodFacts
     * 
     * @return array<string, string>
     */
    public function getAvailableCategories(): array
    {
        return [
            'beverages' => 'Getränke',
            'dairy' => 'Milchprodukte',
            'snacks' => 'Snacks',
            'cereals-and-potatoes' => 'Getreide und Kartoffeln',
            'meat' => 'Fleisch',
            'fish' => 'Fisch',
            'fruits-and-vegetables' => 'Obst und Gemüse',
            'frozen-foods' => 'Tiefkühlkost',
            'bakery' => 'Backwaren',
            'confectionery' => 'Süßwaren',
        ];
    }
    
    /**
     * Search OpenFoodFacts for a specific product by UPC code
     * 
     * @param string $upcCode
     * @return array<string, mixed>|null
     */
    public function searchOpenFoodFactsByUpc(string $upcCode): ?array
    {
        try {
            Log::info('Searching OpenFoodFacts by UPC code', ['upc_code' => $upcCode]);
            
            $response = Http::timeout(15)
                ->get(self::OPENFOODFACTS_API_URL . '/product/' . $upcCode . '.json');
                
            if (!$response->successful()) {
                Log::warning('OpenFoodFacts product lookup failed', [
                    'upc_code' => $upcCode,
                    'status' => $response->status()
                ]);
                return null;
            }
            
            $data = $response->json();
            
            // Check if product was found
            if (empty($data['product']) || $data['status'] !== 1) {
                Log::debug('OpenFoodFacts API returned no product or status != 1', [
                    'status' => $data['status'] ?? 'unknown',
                    'has_product' => !empty($data['product'])
                ]);
                return null;
            }

            Log::debug('OpenFoodFacts API returned product data', [
                'product_name' => $data['product']['product_name'] ?? 'unknown',
                'has_ingredients' => !empty($data['product']['ingredients_text']),
                'available_fields' => array_keys($data['product'])
            ]);
            
            return $this->processOpenFoodFactsProduct($data['product']);
            
        } catch (\Exception $e) {
            Log::error('Error searching OpenFoodFacts by UPC', [
                'upc_code' => $upcCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Search REWE for a product by UPC code
     * 
     * @param string $upcCode
     * @return array<string, mixed>|null
     */
    public function searchReweByUpc(string $upcCode): ?array
    {
        // TODO: Implement real REWE API integration
        Log::info('REWE UPC search not yet implemented', ['upc_code' => $upcCode]);
        return null;
    }
    
    /**
     * Search Edeka for a product by UPC code
     * 
     * @param string $upcCode
     * @return array<string, mixed>|null
     */
    public function searchEdekaByUpc(string $upcCode): ?array
    {
        // TODO: Implement real Edeka API integration
        Log::info('Edeka UPC search not yet implemented', ['upc_code' => $upcCode]);
        return null;
    }
} 