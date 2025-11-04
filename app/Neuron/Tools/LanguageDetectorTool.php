<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use Throwable;

final class LanguageDetectorTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'detect_language',
            'Detects both the input language and any requested target language from user text.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'text',
                type: PropertyType::STRING,
                description: 'User message to analyze.',
                required: true
            ),
        ];
    }

    public function __invoke(string $text): array
    {
        //? Basic input validation
        $text = trim($text);
        if ($text === '') {
            return $this->errorResponse('Text input cannot be empty.');
        }

        $textLower = mb_strtolower($text);

        //? Quick, deterministic detection (no LLM call)
        $explicit = [
            'chinese' => 'zh',
            'japanese' => 'ja',
            'french' => 'fr',
            'spanish' => 'es',
            'german'  => 'de',
            'igbo'    => 'ig',
            'yoruba'  => 'yo',
        ];

        foreach ($explicit as $kw => $code) {
            if (str_contains($textLower, $kw)) {
                return [
                    'source_lang' => 'en',
                    'target_lang' => $code,
                    'intent' => $this->stripLanguageMentions($text, $kw),
                ];
            }
        }

        //? LLM-based detection fallback
        try {
            $provider = new Gemini(
                key: config('services.gemini.key'),
                model: config('services.gemini.model', 'gemini-1.5-flash'),
            );

            $prompt = <<<PROMPT
                You are a language and intent detector. 
                Analyze this message and return JSON only.
                
                Message: "{$text}"

                Return an object like:
                {
                    "source_lang": "en",
                    "target_lang": "fr",
                    "intent": "get global economy news from last week"
                }

                Rules:
                - Always use two-letter ISO codes.
                - If no target translation is requested, set target_lang = source_lang.
                - Do NOT include markdown, code fences, or explanations.
            PROMPT;

            $response = $provider->chat([new UserMessage($prompt)]);
            $resp = $response->getContent() ?? '';

            //? Extract JSON portion safely
            if (preg_match('/\{.*\}/s', $resp, $m)) {
                $jsonText = $m[0];
            } else {
                $jsonText = $resp;
            }

            $data = json_decode($jsonText, true);

            //? Handle invalid JSON
            if (!is_array($data)) {
                Log::warning('LanguageDetectorTool: Malformed response from Gemini', ['response' => $resp]);

                return [
                    'source_lang' => 'en',
                    'target_lang' => 'en',
                    'intent' => $text,
                ];
            }

            return [
                'source_lang' => $data['source_lang'] ?? 'en',
                'target_lang' => $data['target_lang'] ?? ($data['source_lang'] ?? 'en'),
                'intent' => $data['intent'] ?? $text,
            ];
        } catch (Throwable $e) {
            //? Handle LLM/network errors gracefully
            Log::error('LanguageDetectorTool Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to detect language or intent.');
        }
    }

    /**
     * Removes explicit "in <language>" mentions for cleaner intent text.
     */
    protected function stripLanguageMentions(string $text, string $langKeyword): string
    {
        return trim(
            preg_replace(
                '/\b(in|en|in\s+the|en\s+la)\s*' . preg_quote($langKeyword, '/') . '\b/i',
                '',
                $text
            )
        );
    }

    /**
     * Standardized error format for consistency.
     */
    protected function errorResponse(string $message): array
    {
        return [
            'source_lang' => 'en',
            'target_lang' => 'en',
            'intent' => '',
            'error' => [
                'code' => 400,
                'message' => $message,
            ],
        ];
    }
}
