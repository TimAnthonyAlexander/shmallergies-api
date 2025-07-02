<?php

namespace App\Console\Commands;

use App\Services\GPTMessage;
use App\Services\GPTService;
use Illuminate\Console\Command;

class TestGPTService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gpt:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the GPT service configuration and connectivity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing GPT Service...');

        try {
            $gptService = new GPTService();

            // Test basic text completion
            $message = GPTMessage::text('user', 'Hello! Please respond with "GPT service is working correctly!"');
            $response = $gptService->chat([$message], 50);

            $content = $response['choices'][0]['message']['content'] ?? '';

            if (str_contains(strtolower($content), 'working correctly')) {
                $this->info('✅ GPT Service is working correctly!');
                $this->line('Response: ' . $content);
            } else {
                $this->warn('⚠️  GPT Service responded but with unexpected content:');
                $this->line('Response: ' . $content);
            }

            // Display usage information
            $this->newLine();
            $this->info('Usage information:');
            $this->line('Model: ' . ($response['model'] ?? 'Unknown'));
            $this->line('Tokens used: ' . ($response['usage']['total_tokens'] ?? 'Unknown'));
        } catch (\Exception $e) {
            $this->error('❌ GPT Service test failed!');
            $this->error('Error: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'API key')) {
                $this->newLine();
                $this->warn('💡 Make sure you have set OPENAI_API_KEY in your .env file');
                $this->line('   You can get an API key from: https://platform.openai.com/api-keys');
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
