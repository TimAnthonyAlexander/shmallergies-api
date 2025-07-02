<?php

namespace Tests\Unit;

use App\Services\GPTService;
use App\Services\GPTMessage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GPTServiceTest extends TestCase
{
    private GPTService $gptService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up OpenAI API key for testing
        config(['services.openai.api_key' => 'test-api-key']);
        
        $this->gptService = new GPTService();
    }

    /** @test */
    public function throws_exception_when_api_key_is_missing()
    {
        // Clear the config and create a new service instance
        config(['services.openai.api_key' => null]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API key is not configured');

        // Create new service instance after clearing config
        new GPTService();
    }

    /** @test */
    public function can_send_chat_completion_request()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Test response from GPT'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $messages = [
            GPTMessage::text('user', 'Test message')
        ];

        $response = $this->gptService->chat($messages);

        $this->assertEquals($mockResponse, $response);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                   $request->hasHeader('Content-Type', 'application/json');
        });
    }

    /** @test */
    public function chat_method_handles_gpt_message_objects()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Test response'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $messages = [
            GPTMessage::text('user', 'Test message')
        ];

        $response = $this->gptService->chat($messages);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['messages']) && 
                   is_array($body['messages']) &&
                   $body['messages'][0]['role'] === 'user' &&
                   is_array($body['messages'][0]['content']) &&
                   $body['messages'][0]['content'][0]['type'] === 'text' &&
                   $body['messages'][0]['content'][0]['text'] === 'Test message';
        });
    }

    /** @test */
    public function chat_method_handles_array_messages()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Test response'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Test message']
        ];

        $response = $this->gptService->chat($messages);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['messages']) && 
                   $body['messages'][0]['role'] === 'user' &&
                   $body['messages'][0]['content'] === 'Test message';
        });
    }

    /** @test */
    public function throws_exception_on_api_error()
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'API Error'], 500)
        ]);

        $messages = [
            GPTMessage::text('user', 'Test message')
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API request failed');

        $this->gptService->chat($messages);
    }

    /** @test */
    public function can_analyze_ingredient_image()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'ingredients' => [
                                [
                                    'name' => 'sugar',
                                    'allergens' => []
                                ],
                                [
                                    'name' => 'milk',
                                    'allergens' => ['milk']
                                ]
                            ]
                        ])
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $imageBase64 = base64_encode('fake-image-data');
        $result = $this->gptService->analyzeIngredientImage($imageBase64);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ingredients', $result);
        $this->assertCount(2, $result['ingredients']);
        $this->assertEquals('sugar', $result['ingredients'][0]['name']);
        $this->assertEquals('milk', $result['ingredients'][1]['name']);
        $this->assertEquals(['milk'], $result['ingredients'][1]['allergens']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['messages']) && 
                   is_array($body['messages']) &&
                   str_contains($body['messages'][0]['content'][0]['text'], 'Analyze this ingredient list image');
        });
    }

    /** @test */
    public function can_analyze_german_ingredients_text()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'ingredients' => [
                                [
                                    'name' => 'sugar',
                                    'allergens' => []
                                ],
                                [
                                    'name' => 'milk',
                                    'allergens' => ['milk']
                                ]
                            ]
                        ])
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $germanText = 'Zucker, Milch, Weizenmehl';
        $result = $this->gptService->analyzeGermanIngredients($germanText);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ingredients', $result);
        $this->assertCount(2, $result['ingredients']);

        Http::assertSent(function ($request) use ($germanText) {
            $body = $request->data();
            return isset($body['messages']) && 
                   is_array($body['messages'][0]['content']) &&
                   $body['messages'][0]['content'][0]['type'] === 'text' &&
                   str_contains($body['messages'][0]['content'][0]['text'], $germanText);
        });
    }

    /** @test */
    public function throws_exception_when_json_parsing_fails()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Invalid JSON response'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $imageBase64 = base64_encode('fake-image-data');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to parse ingredients from GPT response');

        $this->gptService->analyzeIngredientImage($imageBase64);
    }

    /** @test */
    public function can_extract_json_from_response_with_extra_text()
    {
        $jsonData = [
            'ingredients' => [
                ['name' => 'sugar', 'allergens' => []]
            ]
        ];

        $responseWithExtraText = "Here's the analysis:\n" . json_encode($jsonData) . "\nHope this helps!";

        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => $responseWithExtraText
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $imageBase64 = base64_encode('fake-image-data');
        $result = $this->gptService->analyzeIngredientImage($imageBase64);

        $this->assertEquals($jsonData, $result);
    }

    /** @test */
    public function can_set_custom_model()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Test response'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $this->gptService->setModel('gpt-3.5-turbo');

        $messages = [
            GPTMessage::text('user', 'Test message')
        ];

        $this->gptService->chat($messages);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['model'] === 'gpt-3.5-turbo';
        });
    }

    /** @test */
    public function uses_correct_default_parameters()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Test response'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $messages = [
            GPTMessage::text('user', 'Test message')
        ];

        $this->gptService->chat($messages);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['model'] === 'gpt-4.1-mini' &&
                   $body['max_tokens'] === 1000 &&
                   $body['temperature'] === 0.1;
        });
    }

    /** @test */
    public function respects_custom_max_tokens()
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Test response'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200)
        ]);

        $messages = [
            GPTMessage::text('user', 'Test message')
        ];

        $this->gptService->chat($messages, 1500);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['max_tokens'] === 1500;
        });
    }

    /** @test */
    public function handles_timeout_properly()
    {
        Http::fake([
            'api.openai.com/*' => function () {
                throw new \Exception('Request timeout');
            }
        ]);

        $messages = [
            GPTMessage::text('user', 'Test message')
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Request timeout');

        $this->gptService->chat($messages);
    }
} 