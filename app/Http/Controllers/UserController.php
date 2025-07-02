<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the authenticated user's profile with allergies.
     *
     * @group User
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "User profile retrieved successfully",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "allergies": [
     *       {
     *         "id": 1,
     *         "allergy_text": "peanuts"
     *       }
     *     ]
     *   }
     * }
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'User profile retrieved successfully',
            'user'    => $request->user()->load('allergies'),
        ]);
    }

    /**
     * Check if a product is safe for the authenticated user based on their allergies.
     *
     * @group User
     *
     * @authenticated
     *
     * @urlParam id integer required The product ID to check safety for. Example: 1
     *
     * @response 200 {
     *   "message": "Product safety check completed",
     *   "product": {
     *     "id": 1,
     *     "name": "Coca Cola",
     *     "upc_code": "049000028391"
     *   },
     *   "is_safe": true,
     *   "potential_conflicts": [],
     *   "product_allergens": ["corn"]
     * }
     * @response 404 {
     *   "message": "Product not found"
     * }
     */
    public function checkProductSafety(Request $request, int $id): JsonResponse
    {
        $user = $request->user()->load('allergies');
        $product = Product::with(['ingredients.allergens'])->find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $userAllergens = $user->allergies->pluck('allergy_text')->map(function ($allergy) {
            return strtolower(trim($allergy));
        });

        $productAllergens = $product->ingredients
            ->flatMap(function ($ingredient) {
                return $ingredient->allergens->pluck('name');
            })
            ->map(function ($allergen) {
                return strtolower(trim($allergen));
            });

        $conflicts = $userAllergens->filter(function ($userAllergen) use ($productAllergens) {
            return $productAllergens->some(function ($productAllergen) use ($userAllergen) {
                return str_contains($productAllergen, $userAllergen) || str_contains($userAllergen, $productAllergen);
            });
        });

        return response()->json([
            'message' => 'Product safety check completed',
            'product' => [
                'id'       => $product->id,
                'name'     => $product->name,
                'upc_code' => $product->upc_code,
            ],
            'is_safe'             => $conflicts->isEmpty(),
            'potential_conflicts' => $conflicts->values(),
            'product_allergens'   => $productAllergens->unique()->values(),
        ]);
    }
}
