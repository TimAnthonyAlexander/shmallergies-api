<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GPTService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';
    private string $model = 'gpt-4o-mini';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');

        if (! $this->apiKey) {
            throw new Exception('OpenAI API key is not configured. Please set OPENAI_API_KEY in your environment.');
        }
    }

    /**
     * Send a chat completion request to OpenAI.
     */
    public function chat(array $messages, int $maxTokens = 1000): array
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => array_map(fn ($msg) => $msg instanceof GPTMessage ? $msg->toArray() : $msg, $messages),
            'max_tokens'  => $maxTokens,
            'temperature' => 0.1, // Low temperature for more consistent results
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post($this->baseUrl . '/chat/completions', $payload);

            if (! $response->successful()) {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                throw new Exception('OpenAI API request failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('GPT Service Error', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Analyze ingredient image and extract ingredients with allergens.
     */
    public function analyzeIngredientImage(string $imageBase64, string $mimeType = 'image/jpeg'): array
    {
        $prompt = "Analyze this ingredient list image and extract all ingredients with their potential allergens. 

Please respond with a JSON object in exactly this format:
{
  \"ingredients\": [
    {
      \"name\": \"ingredient name\",
      \"allergens\": [\"allergen1\", \"allergen2\"]
    }
  ]
}

Focus on common allergens like: peanuts, tree nuts, milk/dairy, eggs, wheat/gluten, soy, fish, shellfish, sesame, corn, sulfites.

Be thorough but conservative - only list allergens that are clearly present or likely based on the ingredient name. If an ingredient doesn't contain obvious allergens, use an empty array.

Return ONLY the JSON object, no additional text or explanation.";

        $message = GPTMessage::textWithImage('user', $prompt, $imageBase64, $mimeType);

        $response = $this->chat([$message], 1500);

        $content = $response['choices'][0]['message']['content'] ?? '';

        // Try to parse JSON from the response
        $jsonData = $this->extractJsonFromResponse($content);

        if (! $jsonData || ! isset($jsonData['ingredients'])) {
            throw new Exception('Failed to parse ingredients from GPT response');
        }

        return $jsonData;
    }

    /**
     * Extract JSON from GPT response, handling cases where there might be extra text.
     */
    private function extractJsonFromResponse(string $response): ?array
    {
        // First try to decode the entire response
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // If that fails, try to find JSON within the response
        $pattern = '/\{.*\}/s';
        if (preg_match($pattern, $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Log the response for debugging
        Log::warning('Failed to parse JSON from GPT response', ['response' => $response]);

        return null;
    }

    /**
     * Set the model to use (for testing or different use cases).
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }
}
