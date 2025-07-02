<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAllergyController extends Controller
{
    /**
     * Get user's allergies.
     *
     * @group User Allergies
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "User allergies retrieved successfully",
     *   "allergies": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "allergy_text": "Peanuts",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $allergies = $request->user()->allergies;

        return response()->json([
            'message'   => 'User allergies retrieved successfully',
            'allergies' => $allergies,
        ]);
    }

    /**
     * Add a new allergy for the user.
     *
     * @group User Allergies
     *
     * @authenticated
     *
     * @bodyParam allergy_text string required The allergy description (max 500 characters). Example: Peanuts and tree nuts
     *
     * @response 201 {
     *   "message": "Allergy added successfully",
     *   "allergy": {
     *     "id": 1,
     *     "user_id": 1,
     *     "allergy_text": "Peanuts and tree nuts",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'allergy_text' => 'required|string|max:500|min:2',
        ]);

        // Sanitize input
        $allergyText = strip_tags(trim($request->allergy_text));
        
        // Check for duplicate allergies for this user
        $existingAllergy = $request->user()->allergies()
            ->where('allergy_text', 'LIKE', '%' . $allergyText . '%')
            ->first();
            
        if ($existingAllergy) {
            return response()->json([
                'message' => 'This allergy already exists for your account.',
                'existing_allergy' => $existingAllergy,
            ], 409);
        }

        $allergy = $request->user()->allergies()->create([
            'allergy_text' => $allergyText,
        ]);

        return response()->json([
            'message' => 'Allergy added successfully',
            'allergy' => $allergy,
        ], 201);
    }

    /**
     * Update an existing allergy.
     *
     * @group User Allergies
     *
     * @authenticated
     *
     * @urlParam id integer required The allergy ID. Example: 1
     *
     * @bodyParam allergy_text string required The updated allergy description (max 500 characters). Example: Severe peanut allergy
     *
     * @response 200 {
     *   "message": "Allergy updated successfully",
     *   "allergy": {
     *     "id": 1,
     *     "user_id": 1,
     *     "allergy_text": "Severe peanut allergy",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     * @response 404 {
     *   "message": "Allergy not found"
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'allergy_text' => 'required|string|max:500|min:2',
        ]);

        $allergy = $request->user()->allergies()->find($id);
        
        if (!$allergy) {
            return response()->json([
                'message' => 'Allergy not found',
            ], 404);
        }

        // Sanitize input
        $allergyText = strip_tags(trim($request->allergy_text));
        
        $allergy->update([
            'allergy_text' => $allergyText,
        ]);

        return response()->json([
            'message' => 'Allergy updated successfully',
            'allergy' => $allergy,
        ]);
    }

    /**
     * Delete an allergy.
     *
     * @group User Allergies
     *
     * @authenticated
     *
     * @urlParam id integer required The allergy ID. Example: 1
     *
     * @response 200 {
     *   "message": "Allergy deleted successfully"
     * }
     * @response 404 {
     *   "message": "Allergy not found"
     * }
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $allergy = $request->user()->allergies()->find($id);
        
        if (!$allergy) {
            return response()->json([
                'message' => 'Allergy not found',
            ], 404);
        }
        
        $allergy->delete();

        return response()->json([
            'message' => 'Allergy deleted successfully',
        ]);
    }
}
