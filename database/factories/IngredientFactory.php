<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ingredients = [
            'sugar', 'salt', 'wheat flour', 'water', 'milk', 'eggs', 'butter',
            'vanilla extract', 'baking powder', 'cocoa powder', 'vegetable oil',
            'corn starch', 'soy lecithin', 'natural flavors', 'citric acid'
        ];

        return [
            'product_id' => \App\Models\Product::factory(),
            'title' => fake()->randomElement($ingredients),
        ];
    }

    /**
     * Create ingredient with specific allergens
     */
    public function withAllergens(array $allergens): static
    {
        return $this->afterCreating(function (\App\Models\Ingredient $ingredient) use ($allergens) {
            foreach ($allergens as $allergenName) {
                \App\Models\Allergen::factory()->create([
                    'ingredient_id' => $ingredient->id,
                    'name' => $allergenName,
                ]);
            }
        });
    }
} 