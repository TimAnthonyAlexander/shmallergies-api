<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function authenticated_user_can_create_product_with_image()
    {
        $auth = $this->createAuthenticatedUser();
        $image = UploadedFile::fake()->image('ingredients.jpg', 800, 600);

        $productData = [
            'name' => 'Test Product',
            'upc_code' => '123456789012',
            'ingredient_image' => $image,
        ];

        $response = $this->postJson('/api/products', $productData, $this->authHeaders($auth['token']));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'product' => [
                    'id', 'name', 'upc_code', 'ingredient_image_path', 'ingredients'
                ],
                'ingredient_image_url'
            ])
            ->assertJson([
                'message' => 'Product created successfully',
                'product' => [
                    'name' => 'Test Product',
                    'upc_code' => '123456789012',
                ]
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'upc_code' => '123456789012',
        ]);

        // Verify image was stored
        $product = Product::where('upc_code', '123456789012')->first();
        $this->assertNotNull($product->ingredient_image_path);
        Storage::disk('public')->assertExists($product->ingredient_image_path);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_product()
    {
        $image = UploadedFile::fake()->image('ingredients.jpg');

        $productData = [
            'name' => 'Test Product',
            'upc_code' => '123456789012',
            'ingredient_image' => $image,
        ];

        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('products', ['name' => 'Test Product']);
    }

    /** @test */
    public function cannot_create_product_with_duplicate_upc_code()
    {
        Product::factory()->create(['upc_code' => '123456789012']);
        $auth = $this->createAuthenticatedUser();
        $image = UploadedFile::fake()->image('ingredients.jpg');

        $productData = [
            'name' => 'Test Product',
            'upc_code' => '123456789012',
            'ingredient_image' => $image,
        ];

        $response = $this->postJson('/api/products', $productData, $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['upc_code']);
    }

    /** @test */
    public function cannot_create_product_with_invalid_upc_code()
    {
        $auth = $this->createAuthenticatedUser();
        $image = UploadedFile::fake()->image('ingredients.jpg');

        $productData = [
            'name' => 'Test Product',
            'upc_code' => 'invalid-upc',
            'ingredient_image' => $image,
        ];

        $response = $this->postJson('/api/products', $productData, $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['upc_code']);
    }

    /** @test */
    public function cannot_create_product_without_image()
    {
        $auth = $this->createAuthenticatedUser();

        $productData = [
            'name' => 'Test Product',
            'upc_code' => '123456789012',
        ];

        $response = $this->postJson('/api/products', $productData, $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ingredient_image']);
    }

    /** @test */
    public function cannot_create_product_with_invalid_image_type()
    {
        $auth = $this->createAuthenticatedUser();
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $productData = [
            'name' => 'Test Product',
            'upc_code' => '123456789012',
            'ingredient_image' => $file,
        ];

        $response = $this->postJson('/api/products', $productData, $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ingredient_image']);
    }

    /** @test */
    public function cannot_create_product_with_oversized_image()
    {
        $auth = $this->createAuthenticatedUser();
        $image = UploadedFile::fake()->create('large-image.jpg', 3000, 'image/jpeg'); // 3MB

        $productData = [
            'name' => 'Test Product',
            'upc_code' => '123456789012',
            'ingredient_image' => $image,
        ];

        $response = $this->postJson('/api/products', $productData, $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ingredient_image']);
    }

    /** @test */
    public function can_search_products_by_name()
    {
        Product::factory()->create(['name' => 'Coca Cola']);
        Product::factory()->create(['name' => 'Pepsi Cola']);
        Product::factory()->create(['name' => 'Orange Juice']);

        $response = $this->getJson('/api/products/search?query=cola');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'products' => [
                    '*' => ['id', 'name', 'upc_code', 'ingredient_image_url', 'ingredients_count', 'allergens_count']
                ],
                'pagination'
            ]);

        $products = $response->json('products');
        $this->assertCount(2, $products);
        $this->assertTrue(str_contains(strtolower($products[0]['name']), 'cola'));
        $this->assertTrue(str_contains(strtolower($products[1]['name']), 'cola'));
    }

    /** @test */
    public function can_search_products_by_upc_code()
    {
        Product::factory()->create(['upc_code' => '123456789012']);
        Product::factory()->create(['upc_code' => '987654321098']);

        $response = $this->getJson('/api/products/search?query=123456');

        $response->assertStatus(200);

        $products = $response->json('products');
        $this->assertCount(1, $products);
        $this->assertEquals('123456789012', $products[0]['upc_code']);
    }

    /** @test */
    public function search_requires_query_parameter()
    {
        $response = $this->getJson('/api/products/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    /** @test */
    public function search_respects_limit_parameter()
    {
        Product::factory()->count(15)->create([
            'name' => 'Test Product'
        ]);

        $response = $this->getJson('/api/products/search?query=test&limit=5');

        $response->assertStatus(200);
        $products = $response->json('products');
        $this->assertCount(5, $products);
    }

    /** @test */
    public function search_respects_pagination()
    {
        Product::factory()->count(15)->create([
            'name' => 'Test Product'
        ]);

        $response = $this->getJson('/api/products/search?query=test&limit=5&page=2');

        $response->assertStatus(200);
        $pagination = $response->json('pagination');
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(3, $pagination['last_page']);
    }

    /** @test */
    public function can_get_product_by_id()
    {
        $product = Product::factory()->withIngredients([
            ['name' => 'sugar', 'allergens' => []],
            ['name' => 'milk', 'allergens' => ['milk']],
        ])->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'product' => [
                    'id', 'name', 'upc_code', 'ingredient_image_url',
                    'ingredients' => [
                        '*' => ['id', 'title', 'allergens']
                    ]
                ]
            ])
            ->assertJson([
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'upc_code' => $product->upc_code,
                ]
            ]);

        $ingredients = $response->json('product.ingredients');
        $this->assertCount(2, $ingredients);
    }

    /** @test */
    public function returns_404_for_nonexistent_product()
    {
        $response = $this->getJson('/api/products/999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Product not found'
            ]);
    }

    /** @test */
    public function can_get_product_by_upc_code()
    {
        $product = Product::factory()->create(['upc_code' => '123456789012']);

        $response = $this->getJson('/api/products/upc/123456789012');

        $response->assertStatus(200)
            ->assertJson([
                'product' => [
                    'id' => $product->id,
                    'upc_code' => '123456789012',
                ]
            ]);
    }

    /** @test */
    public function returns_404_for_nonexistent_upc_code()
    {
        $response = $this->getJson('/api/products/upc/999999999999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Product not found'
            ]);
    }

    /** @test */
    public function can_list_all_products()
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'products' => [
                    '*' => ['id', 'name', 'upc_code', 'ingredient_image_url', 'ingredients_count', 'allergens_count']
                ],
                'pagination'
            ]);

        $products = $response->json('products');
        $this->assertCount(3, $products);
    }

    /** @test */
    public function can_filter_products_by_allergens()
    {
        // Product with milk allergen
        Product::factory()->withIngredients([
            ['name' => 'milk', 'allergens' => ['milk']],
        ])->create(['name' => 'Milk Product']);

        // Product with peanuts allergen
        Product::factory()->withIngredients([
            ['name' => 'peanuts', 'allergens' => ['peanuts']],
        ])->create(['name' => 'Peanut Product']);

        // Product with no allergens
        Product::factory()->withIngredients([
            ['name' => 'sugar', 'allergens' => []],
        ])->create(['name' => 'Safe Product']);

        $response = $this->getJson('/api/products/allergens?allergens=milk');

        $response->assertStatus(200);
        $products = $response->json('products');
        $this->assertCount(1, $products);
        $this->assertEquals('Milk Product', $products[0]['name']);
    }

    /** @test */
    public function product_creation_is_rate_limited()
    {
        $auth = $this->createAuthenticatedUser();
        $image = UploadedFile::fake()->image('ingredients.jpg');

        // Attempt to exceed rate limit (10 per minute)
        for ($i = 0; $i < 11; $i++) {
            $productData = [
                'name' => "Test Product {$i}",
                'upc_code' => "12345678901{$i}",
                'ingredient_image' => $image,
            ];

            $response = $this->postJson('/api/products', $productData, $this->authHeaders($auth['token']));
        }

        $response->assertStatus(429); // Too Many Requests
    }

    /** @test */
    public function product_search_is_rate_limited()
    {
        // Attempt to exceed rate limit (60 per minute)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/products/search?query=test');
        }

        $response->assertStatus(429); // Too Many Requests
    }
} 