<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use App\Neuron\Services\NewsApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class NewsFetcherTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'fetch_news',
            'Fetches relevant news articles for a given topic, date range, and language.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Topic or keyword to search news for (e.g., "global economy", "AI advancements").',
                required: true,
            ),
            new ToolProperty(
                name: 'from',
                type: PropertyType::STRING,
                description: 'Start date (YYYY-MM-DD).',
                required: false,
            ),
            new ToolProperty(
                name: 'to',
                type: PropertyType::STRING,
                description: 'End date (YYYY-MM-DD).',
                required: false,
            ),
            new ToolProperty(
                name: 'source_language',
                type: PropertyType::STRING,
                description: 'Source language of the articles (default: en).',
                required: false,
            ),
            new ToolProperty(
                name: 'target_language',
                type: PropertyType::STRING,
                description: 'Target language for summarization or translation (default: en).',
                required: false,
            ),
        ];
    }

    public function __invoke(
        string $query,
        ?string $from = null,
        ?string $to = null,
        ?string $source_language = 'en',
        ?string $target_language = 'en',
    ): string {
        try {
            $query = trim($query);
            if ($query === '') {
                throw new \InvalidArgumentException('Query cannot be empty.');
            }

            $fromDate = $this->validateOrDefaultDate($from, now()->subDays(7));
            $toDate = $this->validateOrDefaultDate($to, now());

            $service = new NewsApiService();
            $result = $service->fetchNews($query, $fromDate, $toDate, $source_language);

            $articles = Arr::get($result, 'articles', []);

            //? Normalize and limit to 10 results
            $normalized = collect($articles)
                ->take(10)
                ->map(fn($a) => [
                    'title' => $a['title'] ?? '',
                    'description' => $a['description'] ?? '',
                    'source' => $a['source']['name'] ?? ($a['source'] ?? ''),
                    'published_at' => $a['publishedAt'] ?? '',
                    'url' => $a['url'] ?? '',
                ])
                ->filter(fn($a) => !empty($a['title']) || !empty($a['description']))
                ->values()
                ->toArray();

            // Return structured JSON output
            return json_encode([
                'query' => $query,
                'source_lang' => $source_language,
                'target_lang' => $target_language,
                'from' => $fromDate,
                'to' => $toDate,
                'articles' => $normalized,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('NewsFetcherTool failed', [
                'query' => $query ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return safe fallback JSON
            return json_encode([
                'query' => $query ?? '',
                'source_lang' => $source_language ?? 'en',
                'target_lang' => $target_language ?? 'en',
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
                'articles' => [],
                'error' => 'Failed to fetch or parse news data. Please try again later.',
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Validate date or fallback to default.
     */
    protected function validateOrDefaultDate(?string $date, Carbon $default): string
    {
        try {
            if (!$date) {
                return $default->toDateString();
            }
            return Carbon::parse($date)->toDateString();
        } catch (\Exception $e) {
            return $default->toDateString();
        }
    }
}
