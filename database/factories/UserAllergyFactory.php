<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserAllergy>
 */
class UserAllergyFactory extends Factory
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
            'user_id' => \App\Models\User::factory(),
            'allergy_text' => fake()->randomElement($commonAllergens),
        ];
    }
} 