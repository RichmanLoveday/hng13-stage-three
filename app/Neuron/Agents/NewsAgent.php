<?php

declare(strict_types=1);

namespace App\Neuron\Agents;

use App\Neuron\Tools\DateParserTool;
use App\Neuron\Tools\LanguageDetectorTool;
use App\Neuron\Tools\NewsFetcherTool;
use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;

final class NewsAgent extends Agent
{
    /**
     * Define which AI provider the agent should use.
     */
    protected function provider(): AIProviderInterface
    {
        // dd("Reached here");
        return new Gemini(
            key: config('services.gemini.key'),
            model: config('services.gemini.model', 'gemini-2.5-flash'),
        );
    }

    /**
     * Define the full workflow instruction for the agent.
     */
    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                "You are a multilingual news pipeline orchestrator.",
                "Execute tools strictly in this order: detect_language → parse_date_range → fetch_news.",
                "IMPORTANT: Always use the SOURCE language for `fetch_news`, not the TARGET language.",
            ],

            steps: [
                "STEP 1 (language): Call `detect_language` with { \"text\": \"<user_message>\" } to extract `source_lang`, `target_lang`, and `intent`.",
                "STEP 2 (dates): Call `parse_date_range` with { \"text\": \"<intent>\" } to derive `from` and `to` dates.",
                "STEP 3 (fetch): Call `fetch_news` with { \"query\": \"<intent>\", \"source_language\": \"<source_lang>\", \"target_language\": \"<target_lang>\", \"from\": \"<from>\", \"to\": \"<to>\" }.",
            ],

            output: [
                "You will receive a JSON-like object after all tools complete successfully:",
                "{",
                "  \"text\": \"<user_query_or_intent>\",",
                "  \"source_lang\": \"<source_language>\",",
                "  \"target_lang\": \"<target_language>\",",
                "  \"from\": \"YYYY-MM-DD\",",
                "  \"to\": \"YYYY-MM-DD\",",
                "  \"articles\": \"<normalized_articles_list>\",",
                "}",
                "Summarize the 'articles' in the given 'target_lang'.",
                "Ensure the summary centers on the 'text' or 'intent'.",
                "Keep it fluent and human-like, not exceeding 10 lines.",
                "Do not use bullet points, markdown, or lists — return plain text only.",
                "If no articles are found, reply with: 'No relevant news was found for your request.'",
            ],
        );
    }

    /**
     * Define the tools used by the agent.
     */
    public function tools(): array
    {
        return [
            LanguageDetectorTool::make(),
            DateParserTool::make(),
            NewsFetcherTool::make(),
        ];
    }
}
