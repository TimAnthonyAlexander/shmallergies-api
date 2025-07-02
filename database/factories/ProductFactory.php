<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' ' . fake()->word(),
            'upc_code' => fake()->unique()->numerify('############'),
            'ingredient_image_path' => 'ingredient-images/test-image.jpg',
        ];
    }

    /**
     * Create product with ingredients
     */
    public function withIngredients(array $ingredients = []): static
    {
        if (empty($ingredients)) {
            $ingredients = [
                ['name' => 'sugar', 'allergens' => []],
                ['name' => 'wheat flour', 'allergens' => ['wheat']],
                ['name' => 'milk', 'allergens' => ['milk']],
            ];
        }

        return $this->afterCreating(function (\App\Models\Product $product) use ($ingredients) {
            foreach ($ingredients as $ingredientData) {
                $ingredient = \App\Models\Ingredient::factory()->create([
                    'product_id' => $product->id,
                    'title' => $ingredientData['name'],
                ]);

                foreach ($ingredientData['allergens'] as $allergenName) {
                    \App\Models\Allergen::factory()->create([
                        'ingredient_id' => $ingredient->id,
                        'name' => $allergenName,
                    ]);
                }
            }
        });
    }

    /**
     * Create product with specific UPC code
     */
    public function withUpc(string $upcCode): static
    {
        return $this->state(fn (array $attributes) => [
            'upc_code' => $upcCode,
        ]);
    }

    /**
     * Create product without image
     */
    public function withoutImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'ingredient_image_path' => null,
        ]);
    }
} 