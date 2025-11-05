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
                required: true
            ),
            new ToolProperty(
                name: 'from',
                type: PropertyType::STRING,
                description: 'Start date (YYYY-MM-DD).',
                required: false
            ),
            new ToolProperty(
                name: 'to',
                type: PropertyType::STRING,
                description: 'End date (YYYY-MM-DD).',
                required: false
            ),
            new ToolProperty(
                name: 'source_language',
                type: PropertyType::STRING,
                description: 'Source language of the articles (default: en).',
                required: false
            ),
            new ToolProperty(
                name: 'target_language',
                type: PropertyType::STRING,
                description: 'Target language for summarization or translation (default: en).',
                required: false
            ),
        ];
    }

    public function __invoke(
        string $query,
        ?string $from = null,
        ?string $to = null,
        ?string $source_language = 'en',
        ?string $target_language = 'en'
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

            $normalized = collect($articles)
                ->map(function ($a) {
                    $title = $a['title'] ?? '';
                    $desc = $a['description'] ?? '';
                    $source = $a['source']['name'] ?? ($a['source'] ?? '');
                    $date = $a['publishedAt'] ?? '';
                    $url = $a['url'] ?? '';

                    return "Title: {$title}\nDescription: {$desc}\nSource: {$source}\nPublished At: {$date}\nURL: {$url}\n";
                })
                ->filter(fn($text) => trim($text) !== '')
                ->implode("\n\n");

            return json_encode([
                'query' => $query,
                'source_lang' => $source_language,
                'target_lang' => $target_language,
                'from' => $fromDate,
                'to' => $toDate,
                'articles' => $normalized ?: 'No relevant articles found.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('NewsFetcherTool failed', [
                'query' => $query ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return json_encode([
                'query' => $query ?? '',
                'source_lang' => $source_language ?? 'en',
                'target_lang' => $target_language ?? 'en',
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
                'articles' => 'No relevant news was found for your request.',
                'error' => 'Failed to fetch or parse news data.',
            ], JSON_PRETTY_PRINT);
        }
    }

    protected function validateOrDefaultDate(?string $date, Carbon $default): string
    {
        try {
            return $date ? Carbon::parse($date)->toDateString() : $default->toDateString();
        } catch (\Exception) {
            return $default->toDateString();
        }
    }
}