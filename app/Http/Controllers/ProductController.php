<?php

namespace App\Http\Controllers;

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\GPTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Create a new product with ingredient image.
     *
     * @group Products
     *
     * @authenticated
     *
     * @bodyParam name string required The product name. Example: Coca Cola
     * @bodyParam upc_code string required The product UPC code (must be unique). Example: 049000028391
     * @bodyParam ingredient_image file required Image of the ingredient list (max 2MB, jpeg/png/jpg/gif).
     *
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
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'upc_code'         => 'required|string|max:255|unique:products|regex:/^[0-9]{8,14}$/',
            'ingredient_image' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Removed gif, added stricter validation
        ], [
            'upc_code.regex' => 'UPC code must be 8-14 digits only.',
        ]);

        DB::beginTransaction();

        try {
            // Sanitize inputs
            $name = strip_tags($request->name);
            $upcCode = preg_replace('/[^0-9]/', '', $request->upc_code);

            // Validate file content type
            $imageFile = $request->file('ingredient_image');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $imageFile->getPathname());
            finfo_close($finfo);
            
            if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
                throw new \Exception('Invalid file type. Only JPEG and PNG files are allowed.');
            }

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('ingredient_image')) {
                $imagePath = $request->file('ingredient_image')->store('ingredient-images', 'public');
            }

            $product = Product::create([
                'name'                  => $name,
                'upc_code'              => $upcCode,
                'ingredient_image_path' => $imagePath,
            ]);

            // Process the ingredient image with GPT
            if ($imagePath && $request->hasFile('ingredient_image')) {
                $this->processIngredientImage($product, $request->file('ingredient_image'));
            }

            DB::commit();

            return response()->json([
                'message'              => 'Product created successfully',
                'product'              => $product->load('ingredients.allergens'),
                'ingredient_image_url' => $imagePath ? Storage::url($imagePath) : null,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded image if processing failed
            if (isset($imagePath) && $imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            Log::error('Product creation failed', [
                'error'        => $e->getMessage(),
                'product_name' => $request->name ?? 'unknown',
                'upc_code'     => $request->upc_code ?? 'unknown',
                'user_id'      => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'Product creation failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while creating the product.',
            ], 500);
        }
    }

    /**
     * Search products by UPC code or name.
     *
     * @group Products
     *
     * @queryParam query string required Search term for product name or UPC code. Example: coca
     * @queryParam limit integer optional Maximum number of results (1-50). Defaults to 10. Example: 20
     * @queryParam page integer optional Page number for pagination. Defaults to 1. Example: 2
     *
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
     *   "pagination": {
     *     "current_page": 1,
     *     "total": 1,
     *     "per_page": 10,
     *     "last_page": 1
     *   }
     * }
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:255',
            'limit' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = strip_tags($request->input('query'));
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        // Use proper parameter binding to prevent SQL injection
        $products = Product::where(function ($q) use ($query) {
                $q->where('name', 'LIKE', '%' . $query . '%')
                  ->orWhere('upc_code', 'LIKE', '%' . $query . '%');
            })
            ->with(['ingredients.allergens'])
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'message'  => 'Search completed',
            'products' => collect($products->items())->map(function ($product) {
                return [
                    'id'                   => $product->id,
                    'name'                 => $product->name,
                    'upc_code'             => $product->upc_code,
                    'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                    'ingredients_count'    => $product->ingredients->count(),
                    'allergens_count'      => $product->ingredients->sum(function ($ingredient) {
                        return $ingredient->allergens->count();
                    }),
                    'created_at' => $product->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Get product details with ingredients and allergens.
     *
     * @group Products
     *
     * @urlParam id integer required The product ID. Example: 1
     *
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
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['ingredients.allergens'])->find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Product retrieved successfully',
            'product' => [
                'id'                   => $product->id,
                'name'                 => $product->name,
                'upc_code'             => $product->upc_code,
                'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                'created_at'           => $product->created_at,
                'updated_at'           => $product->updated_at,
                'ingredients'          => $product->ingredients->map(function ($ingredient) {
                    return [
                        'id'        => $ingredient->id,
                        'title'     => $ingredient->title,
                        'allergens' => $ingredient->allergens->map(function ($allergen) {
                            return [
                                'id'   => $allergen->id,
                                'name' => $allergen->name,
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get product by UPC code.
     *
     * @group Products
     *
     * @urlParam upcCode string required The UPC code to search for. Example: 049000028391
     *
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
     */
    public function getByUpc(string $upcCode): JsonResponse
    {
        // Sanitize UPC code - only allow digits
        $sanitizedUpc = preg_replace('/[^0-9]/', '', $upcCode);
        
        // Validate UPC code format
        if (empty($sanitizedUpc) || strlen($sanitizedUpc) < 8 || strlen($sanitizedUpc) > 14) {
            return response()->json([
                'message' => 'Invalid UPC code format. UPC codes must be 8-14 digits.',
            ], 400);
        }

        $product = Product::where('upc_code', $sanitizedUpc)
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
     * Get all products with pagination.
     *
     * @group Products
     *
     * @queryParam limit integer optional Maximum number of results per page (1-50). Defaults to 10. Example: 20
     * @queryParam page integer optional Page number for pagination. Defaults to 1. Example: 2
     *
     * @response 200 {
     *   "message": "Products retrieved successfully",
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
     *   "pagination": {
     *     "current_page": 1,
     *     "total": 100,
     *     "per_page": 10,
     *     "last_page": 10
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
        ]);

        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        $products = Product::with(['ingredients.allergens'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'message'  => 'Products retrieved successfully',
            'products' => collect($products->items())->map(function ($product) {
                return [
                    'id'                   => $product->id,
                    'name'                 => $product->name,
                    'upc_code'             => $product->upc_code,
                    'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                    'ingredients_count'    => $product->ingredients->count(),
                    'allergens_count'      => $product->ingredients->sum(function ($ingredient) {
                        return $ingredient->allergens->count();
                    }),
                    'created_at' => $product->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Get products by allergens.
     *
     * @group Products
     *
     * @queryParam allergens string required Comma-separated list of allergen names. Example: milk,eggs,peanuts
     * @queryParam limit integer optional Maximum number of results per page (1-50). Defaults to 10. Example: 20
     * @queryParam page integer optional Page number for pagination. Defaults to 1. Example: 2
     *
     * @response 200 {
     *   "message": "Products retrieved successfully",
     *   "products": [
     *     {
     *       "id": 1,
     *       "name": "Coca Cola",
     *       "upc_code": "049000028391",
     *       "ingredient_image_url": "/storage/ingredient-images/abc123.jpg",
     *       "allergens": ["corn"],
     *       "ingredients_count": 5,
     *       "created_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "total": 10,
     *     "per_page": 10,
     *     "last_page": 1
     *   }
     * }
     */
    public function getByAllergens(Request $request): JsonResponse
    {
        $request->validate([
            'allergens' => 'required|string|max:500',
            'limit' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
        ]);

        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        
        // Sanitize and parse allergens
        $allergenString = strip_tags($request->input('allergens'));
        $allergens = array_map('trim', explode(',', $allergenString));
        $allergens = array_filter($allergens, function($allergen) {
            return !empty($allergen) && strlen($allergen) <= 50;
        });

        if (empty($allergens)) {
            return response()->json([
                'message' => 'No valid allergens provided',
                'products' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total' => 0,
                    'per_page' => $limit,
                    'last_page' => 1,
                ],
            ]);
        }

        $products = Product::whereHas('ingredients.allergens', function ($query) use ($allergens) {
            $query->whereIn('name', $allergens);
        })
        ->with(['ingredients.allergens'])
        ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'message' => 'Products retrieved successfully',
            'products' => collect($products->items())->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'upc_code' => $product->upc_code,
                    'ingredient_image_url' => $product->ingredient_image_path ? Storage::url($product->ingredient_image_path) : null,
                    'allergens' => $product->ingredients->flatMap(function ($ingredient) {
                        return $ingredient->allergens->pluck('name');
                    })->unique()->values(),
                    'ingredients_count' => $product->ingredients->count(),
                    'created_at' => $product->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Process ingredient image using GPT to extract ingredients and allergens.
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
                    'title'      => $ingredientData['name'],
                ]);

                // Save allergens for this ingredient
                if (! empty($ingredientData['allergens'])) {
                    foreach ($ingredientData['allergens'] as $allergenName) {
                        Allergen::create([
                            'ingredient_id' => $ingredient->id,
                            'name'          => $allergenName,
                        ]);
                    }
                }
            }

            Log::info('Ingredient analysis completed', [
                'product_id'        => $product->id,
                'ingredients_count' => count($analysis['ingredients']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process ingredient image', [
                'product_id' => $product->id,
                'error'      => $e->getMessage(),
            ]);

            // Re-throw to be handled by the calling method
            throw $e;
        }
    }
}
