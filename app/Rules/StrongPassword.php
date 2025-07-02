<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check minimum length
        if (strlen($value) < 12) {
            $fail('The :attribute must be at least 12 characters long.');
            return;
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
            return;
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
            return;
        }

        // Check for at least one digit
        if (!preg_match('/\d/', $value)) {
            $fail('The :attribute must contain at least one number.');
            return;
        }

        // Check for at least one special character
        if (!preg_match('/[@$!%*?&]/', $value)) {
            $fail('The :attribute must contain at least one special character (@$!%*?&).');
            return;
        }

        // Check for common weak patterns
        $weakPatterns = [
            '/(.)\1{2,}/', // Three or more consecutive identical characters
            '/123456/',    // Sequential numbers
            '/abcdef/',    // Sequential letters
            '/password/i', // Contains "password"
            '/qwerty/i',   // Contains "qwerty"
        ];

        foreach ($weakPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail('The :attribute contains a common weak pattern and is not secure enough.');
                return;
            }
        }
    }
} 