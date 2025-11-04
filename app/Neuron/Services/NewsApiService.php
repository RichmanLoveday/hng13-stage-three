<?php

namespace App\Neuron\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsApiService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.news.key');
        $this->baseUrl = rtrim(config('services.news.url'), '/');
    }

    /**
     * Fetch news articles from an external API.
     *
     * @param string $query Topic or keyword to search for
     * @param string $from  Start date (YYYY-MM-DD)
     * @param string $to    End date (YYYY-MM-DD)
     * @param string $language ISO language code (e.g. 'en', 'fr')
     * @param int $pageSize Number of results to return (default 20)
     * @return array Structured API response
     */
    public function fetchNews(
        string $query,
        string $from,
        string $to,
        string $language,
        int $pageSize = 50
    ): array {
        $endpoint = "{$this->baseUrl}/everything";

        try {
            $response = Http::timeout(10)
                ->retry(2, 200)
                ->get($endpoint, [
                    'q'        => $query,
                    'from'     => $from,
                    'to'       => $to,
                    'language' => $language,
                    'sortBy'   => 'relevancy',
                    'pageSize' => $pageSize,
                    'apiKey'   => $this->apiKey,
                ]);

            if ($response->failed()) {
                Log::warning('News API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success'  => false,
                    'message'  => 'Unable to fetch articles from news API.',
                    'code'     => $response->status(),
                    'articles' => [],
                ];
            }

            $articles = collect($response->json('articles', []))
                ->map(fn($a) => [
                    'title'        => $a['title'] ?? '',
                    'description'  => $a['description'] ?? '',
                    'url'          => $a['url'] ?? '',
                    'source'       => $a['source']['name'] ?? 'Unknown',
                    'published_at' => $a['publishedAt'] ?? null,
                ])
                ->filter(fn($a) => filled($a['title']) && filled($a['description']))
                ->take($pageSize)
                ->values()
                ->toArray();

            return [
                'success'  => true,
                'articles' => $articles,
                'count'    => count($articles),
            ];
        } catch (Throwable $e) {
            Log::error('NewsApiService::fetchNews error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return [
                'success'  => false,
                'message'  => 'An unexpected error occurred while fetching news.',
                'error'    => $e->getMessage(),
                'articles' => [],
            ];
        }
    }
}