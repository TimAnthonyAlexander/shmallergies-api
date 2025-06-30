<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Ingredient;
use App\Models\Allergen;
use App\Services\GPTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Create a new product with ingredient image
     * 
     * @group Products
     * @authenticated
     * @bodyParam name string required The product name. Example: Coca Cola
     * @bodyParam upc_code string required The product UPC code (must be unique). Example: 049000028391
     * @bodyParam ingredient_image file required Image of the ingredient list (max 2MB, jpeg/png/jpg/gif).
     * @response 201 {
     *   "message": "Product created successfully",
     *   "product": {
     *     "id": 1,
     *     "name": "Coca Cola",
     *     "upc_code": "049000028391",
     *     "ingredient_image_path": "ingredient-images/abc123.jpg",
     *     "ingredients": []
     *   },
     *   "ingredient_image_url": "/storage/ingredient-images/abc123.jpg"
     * }
     * @response 422 {
     *   "message": "The upc code has already been taken.",
     *   "errors": {
     *     "upc_code": ["The upc code has already been taken."]
     *   }
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'upc_code' => 'required|string|max:255|unique:products',
            'ingredient_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        DB::beginTransaction();
        
        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('ingredient_image')) {
                $imagePath = $request->file('ingredient_image')->store('ingredient-images', 'public');
            }

            $product = Product::create([
                'name' => $request->name,
                'upc_code' => $request->upc_code,
                'ingredient_image_path' => $imagePath,
            ]);

            // Process the ingredient image with GPT
            if ($imagePath && $request->hasFile('ingredient_image')) {
                $this->processIngredientImage($product, $request->file('ingredient_image'));
            }

            DB::commit();

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product->load('ingredients.allergens'),
                'ingredient_image_url' => $imagePath ? Storage::url($imagePath) : null,
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded image if processing failed
            if (isset($imagePath) && $imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            
            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
                'product_name' => $request->name,
                'upc_code' => $request->upc_code
            ]);

            return response()->json([
                'message' => 'Product creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search products by UPC code or name
     * 
     * @group Products
     * @queryParam query string required Search term for product name or UPC code. Example: coca
     * @queryParam limit integer optional Maximum number of results (1-50). Defaults to 10. Example: 20
     * @response 200 {
     *   "message": "Search completed",
     *   "products": [
     *     {
     *       "id": 1,
     *       "name": "Coca Cola",
     *       "upc_code": "049000028391",
     *       "ingredient_image_url": "/storage/ingredient-images/abc123.jpg",
     *       "ingredients_count": 5,
     *       "allergens_count": 2,
     *       "created_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ],
     *   "total": 1
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 10);

        $products = Product::where('name', 'LIKE', "%{$query}%")
            ->orWhere('upc_code', 'LIKE', "%{$query}%")
            ->with(['ingredients.allergens'])
            ->limit($limit)
            ->get();

        return response()->json([
            'message' => 'Search completed',
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'upc_code' => $product->upc_code,
                    'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                    'ingredients_count' => $product->ingredients->count(),
                    'allergens_count' => $product->ingredients->sum(function ($ingredient) {
                        return $ingredient->allergens->count();
                    }),
                    'created_at' => $product->created_at,
                ];
            }),
            'total' => $products->count(),
        ]);
    }

    /**
     * Get product details with ingredients and allergens
     * 
     * @group Products
     * @urlParam id integer required The product ID. Example: 1
     * @response 200 {
     *   "message": "Product retrieved successfully",
     *   "product": {
     *     "id": 1,
     *     "name": "Coca Cola",
     *     "upc_code": "049000028391",
     *     "ingredient_image_url": "/storage/ingredient-images/abc123.jpg",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "ingredients": [
     *       {
     *         "id": 1,
     *         "title": "Carbonated Water",
     *         "allergens": []
     *       },
     *       {
     *         "id": 2,
     *         "title": "High Fructose Corn Syrup",
     *         "allergens": [
     *           {
     *             "id": 1,
     *             "name": "Corn"
     *           }
     *         ]
     *       }
     *     ]
     *   }
     * }
     * @response 404 {
     *   "message": "Product not found"
     * }
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['ingredients.allergens'])->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Product retrieved successfully',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'upc_code' => $product->upc_code,
                'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'ingredients' => $product->ingredients->map(function ($ingredient) {
                    return [
                        'id' => $ingredient->id,
                        'title' => $ingredient->title,
                        'allergens' => $ingredient->allergens->map(function ($allergen) {
                            return [
                                'id' => $allergen->id,
                                'name' => $allergen->name,
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get product by UPC code
     * 
     * @group Products
     * @urlParam upcCode string required The product UPC code. Example: 049000028391
     * @response 200 {
     *   "message": "Product retrieved successfully",
     *   "product": {
     *     "id": 1,
     *     "name": "Coca Cola",
     *     "upc_code": "049000028391",
     *     "ingredient_image_url": "/storage/ingredient-images/abc123.jpg",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z",
     *     "ingredients": []
     *   }
     * }
     * @response 404 {
     *   "message": "Product not found"
     * }
     *
     * @param string $upcCode
     * @return JsonResponse
     */
    public function getByUpc(string $upcCode): JsonResponse
    {
        $product = Product::where('upc_code', $upcCode)
            ->with(['ingredients.allergens'])
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Product retrieved successfully',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'upc_code' => $product->upc_code,
                'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'ingredients' => $product->ingredients->map(function ($ingredient) {
                    return [
                        'id' => $ingredient->id,
                        'title' => $ingredient->title,
                        'allergens' => $ingredient->allergens->map(function ($allergen) {
                            return [
                                'id' => $allergen->id,
                                'name' => $allergen->name,
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get all products with pagination
     * 
     * @group Products
     * @queryParam page integer optional Page number for pagination. Defaults to 1. Example: 1
     * @queryParam per_page integer optional Number of products per page (1-50). Defaults to 15. Example: 20
     * @response 200 {
     *   "message": "Products retrieved successfully",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Coca Cola",
     *       "upc_code": "049000028391",
     *       "ingredient_image_url": "/storage/ingredient-images/abc123.jpg",
     *       "ingredients_count": 5,
     *       "allergens_count": 2,
     *       "created_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ],
     *   "current_page": 1,
     *   "last_page": 1,
     *   "per_page": 15,
     *   "total": 1
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $perPage = $request->input('per_page', 15);

        $products = Product::with(['ingredients.allergens'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ]);
    }

    /**
     * Get products with specific allergens
     * 
     * @group Products
     * @queryParam allergens string required Comma-separated list of allergen names to filter by. Example: peanuts,dairy
     * @queryParam limit integer optional Maximum number of results (1-50). Defaults to 10. Example: 20
     * @response 200 {
     *   "message": "Products with allergens retrieved successfully",
     *   "products": [
     *     {
     *       "id": 1,
     *       "name": "Coca Cola",
     *       "upc_code": "049000028391",
     *       "ingredient_image_url": "/storage/ingredient-images/abc123.jpg",
     *       "matching_allergens": ["peanuts"],
     *       "ingredients_count": 5,
     *       "allergens_count": 2,
     *       "created_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ],
     *   "total": 1,
     *   "searched_allergens": ["peanuts", "dairy"]
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getByAllergens(Request $request): JsonResponse
    {
        $request->validate([
            'allergens' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $allergensList = array_map('trim', explode(',', $request->input('allergens')));
        $limit = $request->input('limit', 10);

        $products = Product::whereHas('ingredients.allergens', function ($query) use ($allergensList) {
            $query->whereIn('name', $allergensList);
        })
        ->with(['ingredients.allergens'])
        ->limit($limit)
        ->get();

        return response()->json([
            'message' => 'Products with allergens retrieved successfully',
            'products' => $products->map(function ($product) use ($allergensList) {
                $matchingAllergens = $product->ingredients
                    ->flatMap(function ($ingredient) {
                        return $ingredient->allergens->pluck('name');
                    })
                    ->filter(function ($allergen) use ($allergensList) {
                        return in_array(strtolower($allergen), array_map('strtolower', $allergensList));
                    })
                    ->unique()
                    ->values();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'upc_code' => $product->upc_code,
                    'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                    'matching_allergens' => $matchingAllergens,
                    'ingredients_count' => $product->ingredients->count(),
                    'allergens_count' => $product->ingredients->sum(function ($ingredient) {
                        return $ingredient->allergens->count();
                    }),
                    'created_at' => $product->created_at,
                ];
            }),
            'total' => $products->count(),
            'searched_allergens' => $allergensList,
        ]);
    }

    /**
     * Process ingredient image using GPT to extract ingredients and allergens
     */
    private function processIngredientImage(Product $product, $imageFile): void
    {
        try {
            // Convert image to base64
            $imageContent = file_get_contents($imageFile->getRealPath());
            $imageBase64 = base64_encode($imageContent);
            $mimeType = $imageFile->getClientMimeType();

            // Initialize GPT service
            $gptService = new GPTService();
            
            // Analyze the ingredient image
            $analysis = $gptService->analyzeIngredientImage($imageBase64, $mimeType);
            
            // Save ingredients and allergens to database
            foreach ($analysis['ingredients'] as $ingredientData) {
                $ingredient = Ingredient::create([
                    'product_id' => $product->id,
                    'title' => $ingredientData['name'],
                ]);

                // Save allergens for this ingredient
                if (!empty($ingredientData['allergens'])) {
                    foreach ($ingredientData['allergens'] as $allergenName) {
                        Allergen::create([
                            'ingredient_id' => $ingredient->id,
                            'name' => $allergenName,
                        ]);
                    }
                }
            }

            Log::info('Ingredient analysis completed', [
                'product_id' => $product->id,
                'ingredients_count' => count($analysis['ingredients']),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process ingredient image', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            
            // Re-throw to be handled by the calling method
            throw $e;
        }
    }
} 