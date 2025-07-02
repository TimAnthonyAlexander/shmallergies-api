<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ScheduledGermanScraping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:german-scheduled
                          {--force : Force scraping even if recently run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled German product scraping with optimized batches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🇩🇪 Starting scheduled German product scraping...');

        // Check if we should run (avoid running too frequently)
        if (!$this->shouldRun()) {
            $this->info('Scraping skipped - ran recently. Use --force to override.');
            return Command::SUCCESS;
        }

        $currentProductCount = Product::count();
        $this->line("Current products in database: {$currentProductCount}");

        // Define scraping strategy based on database size
        $strategy = $this->getScrapingStrategy($currentProductCount);
        
        $this->info("Using strategy: {$strategy['name']}");
        $this->line("Batch size: {$strategy['batch_size']}");
        $this->line("Categories: " . implode(', ', $strategy['categories']));

        $totalProcessed = 0;
        $totalCreated = 0;

        // Process each category in the strategy
        foreach ($strategy['categories'] as $category) {
            $this->line("\n📦 Processing category: {$category}");
            
            try {
                // Run the scraping command for this category
                $process = Process::path(base_path('api'))
                    ->command([
                        'php', 'artisan', 'scrape:german-products',
                        '--limit=' . $strategy['batch_size'],
                        '--category=' . $category,
                        '--source=openfoodfacts'
                    ]);

                $result = $process->run();

                if ($result->successful()) {
                    // Parse the output to get statistics
                    $output = $result->output();
                    $stats = $this->parseScrapingOutput($output);
                    
                    $totalProcessed += $stats['processed'];
                    $totalCreated += $stats['created'];
                    
                    $this->line("✅ {$category}: {$stats['created']} created, {$stats['skipped']} skipped");
                } else {
                    $this->error("❌ Failed to scrape {$category}: " . $result->errorOutput());
                }

                // Pause between categories to be respectful to APIs
                sleep(2);

            } catch (\Exception $e) {
                $this->error("❌ Error processing {$category}: " . $e->getMessage());
            }
        }

        $this->info("\n🎉 Scheduled scraping completed!");
        $this->line("Total processed: {$totalProcessed}");
        $this->line("Total created: {$totalCreated}");
        $this->line("Database now contains: " . Product::count() . " products");

        // Update last run timestamp
        $this->updateLastRunTimestamp();

        return Command::SUCCESS;
    }

    /**
     * Check if scraping should run based on last execution time
     */
    private function shouldRun(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $lastRunFile = storage_path('app/last_german_scraping.txt');
        
        if (!file_exists($lastRunFile)) {
            return true;
        }

        $lastRun = (int) file_get_contents($lastRunFile);
        $hoursSinceLastRun = (time() - $lastRun) / 3600;

        // Run if it's been more than 8 hours since last run
        return $hoursSinceLastRun >= 8;
    }

    /**
     * Update the last run timestamp
     */
    private function updateLastRunTimestamp(): void
    {
        $lastRunFile = storage_path('app/last_german_scraping.txt');
        file_put_contents($lastRunFile, time());
    }

    /**
     * Get scraping strategy based on current database size
     * 
     * @return array<string, mixed>
     */
    private function getScrapingStrategy(int $currentProductCount): array
    {
        if ($currentProductCount < 1000) {
            // Bootstrap phase - get variety of products
            return [
                'name' => 'Bootstrap',
                'batch_size' => 50,
                'categories' => [
                    'beverages',
                    'dairy', 
                    'snacks',
                    'cereals-and-potatoes',
                    'bakery',
                    'confectionery'
                ]
            ];
        } elseif ($currentProductCount < 5000) {
            // Growth phase - expand categories
            return [
                'name' => 'Growth',
                'batch_size' => 30,
                'categories' => [
                    'meat',
                    'fish',
                    'fruits-and-vegetables',
                    'frozen-foods',
                    'dairy',
                    'beverages'
                ]
            ];
        } else {
            // Maintenance phase - smaller batches, focus on gaps
            return [
                'name' => 'Maintenance',
                'batch_size' => 20,
                'categories' => [
                    'snacks',
                    'confectionery',
                    'beverages'
                ]
            ];
        }
    }

    /**
     * Parse scraping command output to extract statistics
     * 
     * @return array<string, int>
     */
    private function parseScrapingOutput(string $output): array
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        // Extract numbers from output lines like "Created: 25"
        if (preg_match('/Created: (\d+)/', $output, $matches)) {
            $stats['created'] = (int) $matches[1];
        }
        
        if (preg_match('/Processed: (\d+)/', $output, $matches)) {
            $stats['processed'] = (int) $matches[1];
        }
        
        if (preg_match('/Skipped: (\d+)/', $output, $matches)) {
            $stats['skipped'] = (int) $matches[1];
        }
        
        if (preg_match('/Updated: (\d+)/', $output, $matches)) {
            $stats['updated'] = (int) $matches[1];
        }
        
        if (preg_match('/Errors: (\d+)/', $output, $matches)) {
            $stats['errors'] = (int) $matches[1];
        }

        return $stats;
    }
} 