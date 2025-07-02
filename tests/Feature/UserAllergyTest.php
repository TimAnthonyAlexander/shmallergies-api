<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\UserAllergy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAllergyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_get_their_allergies()
    {
        $auth = $this->createAuthenticatedUser();
        
        UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'peanuts'
        ]);
        
        UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'milk'
        ]);

        $response = $this->getJson('/api/user/allergies', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'allergies' => [
                    '*' => ['id', 'allergy_text', 'created_at', 'updated_at']
                ]
            ]);

        $allergies = $response->json('allergies');
        $this->assertCount(2, $allergies);
        $this->assertEquals('peanuts', $allergies[0]['allergy_text']);
        $this->assertEquals('milk', $allergies[1]['allergy_text']);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_allergies()
    {
        $response = $this->getJson('/api/user/allergies');

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_add_allergy()
    {
        $auth = $this->createAuthenticatedUser();

        $allergyData = [
            'allergy_text' => 'shellfish'
        ];

        $response = $this->postJson('/api/user/allergies', $allergyData, $this->authHeaders($auth['token']));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'allergy' => ['id', 'allergy_text', 'created_at', 'updated_at']
            ])
            ->assertJson([
                'message' => 'Allergy added successfully',
                'allergy' => [
                    'allergy_text' => 'shellfish'
                ]
            ]);

        $this->assertDatabaseHas('user_allergies', [
            'user_id' => $auth['user']->id,
            'allergy_text' => 'shellfish'
        ]);
    }

    /** @test */
    public function cannot_add_empty_allergy()
    {
        $auth = $this->createAuthenticatedUser();

        $allergyData = [
            'allergy_text' => ''
        ];

        $response = $this->postJson('/api/user/allergies', $allergyData, $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allergy_text']);
    }

    /** @test */
    public function cannot_add_duplicate_allergy()
    {
        $auth = $this->createAuthenticatedUser();
        
        UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'peanuts'
        ]);

        $allergyData = [
            'allergy_text' => 'peanuts'
        ];

        $response = $this->postJson('/api/user/allergies', $allergyData, $this->authHeaders($auth['token']));

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'This allergy already exists for your account.'
            ]);
    }

    /** @test */
    public function authenticated_user_can_update_their_allergy()
    {
        $auth = $this->createAuthenticatedUser();
        
        $allergy = UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'peanuts'
        ]);

        $updateData = [
            'allergy_text' => 'tree nuts'
        ];

        $response = $this->putJson("/api/user/allergies/{$allergy->id}", $updateData, $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'allergy' => ['id', 'allergy_text', 'updated_at']
            ])
            ->assertJson([
                'message' => 'Allergy updated successfully',
                'allergy' => [
                    'id' => $allergy->id,
                    'allergy_text' => 'tree nuts'
                ]
            ]);

        $this->assertDatabaseHas('user_allergies', [
            'id' => $allergy->id,
            'allergy_text' => 'tree nuts'
        ]);
    }

    /** @test */
    public function user_cannot_update_another_users_allergy()
    {
        $auth1 = $this->createAuthenticatedUser();
        $auth2 = $this->createAuthenticatedUser();
        
        $allergy = UserAllergy::factory()->create([
            'user_id' => $auth1['user']->id,
            'allergy_text' => 'peanuts'
        ]);

        $updateData = [
            'allergy_text' => 'tree nuts'
        ];

        $response = $this->putJson("/api/user/allergies/{$allergy->id}", $updateData, $this->authHeaders($auth2['token']));

        $response->assertStatus(404);

        // Verify allergy wasn't updated
        $this->assertDatabaseHas('user_allergies', [
            'id' => $allergy->id,
            'allergy_text' => 'peanuts'
        ]);
    }

    /** @test */
    public function authenticated_user_can_delete_their_allergy()
    {
        $auth = $this->createAuthenticatedUser();
        
        $allergy = UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'peanuts'
        ]);

        $response = $this->deleteJson("/api/user/allergies/{$allergy->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Allergy deleted successfully'
            ]);

        $this->assertDatabaseMissing('user_allergies', [
            'id' => $allergy->id
        ]);
    }

    /** @test */
    public function user_cannot_delete_another_users_allergy()
    {
        $auth1 = $this->createAuthenticatedUser();
        $auth2 = $this->createAuthenticatedUser();
        
        $allergy = UserAllergy::factory()->create([
            'user_id' => $auth1['user']->id,
            'allergy_text' => 'peanuts'
        ]);

        $response = $this->deleteJson("/api/user/allergies/{$allergy->id}", [], $this->authHeaders($auth2['token']));

        $response->assertStatus(404);

        // Verify allergy still exists
        $this->assertDatabaseHas('user_allergies', [
            'id' => $allergy->id
        ]);
    }

    /** @test */
    public function user_can_check_product_safety()
    {
        $auth = $this->createAuthenticatedUser();
        
        // User is allergic to milk
        UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'milk'
        ]);

        // Product contains milk
        $product = Product::factory()->withIngredients([
            ['name' => 'milk', 'allergens' => ['milk']],
            ['name' => 'sugar', 'allergens' => []]
        ])->create();

        $response = $this->getJson("/api/user/product-safety/{$product->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'product' => ['id', 'name', 'upc_code'],
                'is_safe',
                'potential_conflicts',
                'product_allergens'
            ])
            ->assertJson([
                'is_safe' => false
            ]);

        $conflicts = $response->json('potential_conflicts');
        $this->assertContains('milk', $conflicts);
    }

    /** @test */
    public function user_can_check_safe_product()
    {
        $auth = $this->createAuthenticatedUser();
        
        // User is allergic to peanuts
        UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'peanuts'
        ]);

        // Product doesn't contain peanuts
        $product = Product::factory()->withIngredients([
            ['name' => 'sugar', 'allergens' => []],
            ['name' => 'salt', 'allergens' => []]
        ])->create();

        $response = $this->getJson("/api/user/product-safety/{$product->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'is_safe' => true
            ]);

        $conflicts = $response->json('potential_conflicts');
        $this->assertEmpty($conflicts);
    }

    /** @test */
    public function returns_404_for_nonexistent_product_safety_check()
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/user/product-safety/999', $this->authHeaders($auth['token']));

        $response->assertStatus(404);
    }

    /** @test */
    public function user_profile_includes_allergies()
    {
        $auth = $this->createAuthenticatedUser();
        
        UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'peanuts'
        ]);
        
        UserAllergy::factory()->create([
            'user_id' => $auth['user']->id,
            'allergy_text' => 'milk'
        ]);

        $response = $this->getJson('/api/user', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id', 'name', 'email', 'email_verified_at',
                    'allergies' => [
                        '*' => ['id', 'allergy_text']
                    ]
                ]
            ]);

        $allergies = $response->json('user.allergies');
        $this->assertCount(2, $allergies);
    }
} 