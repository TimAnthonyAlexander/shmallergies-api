<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Allergen>
 */
class AllergenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $commonAllergens = [
            'peanuts', 'tree nuts', 'milk', 'eggs', 'wheat', 'soy', 
            'fish', 'shellfish', 'sesame', 'corn', 'sulfites'
        ];

        return [
            'ingredient_id' => \App\Models\Ingredient::factory(),
            'name' => fake()->randomElement($commonAllergens),
        ];
    }
} 