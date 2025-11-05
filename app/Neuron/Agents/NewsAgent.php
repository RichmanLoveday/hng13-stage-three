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
        // return (string) new SystemPrompt(
        //     background: [
        //         "You are a multilingual news pipeline orchestrator.",
        //         "Execute tools strictly in this order: detect_language → parse_date_range → fetch_news.",
        //         "IMPORTANT: Always use the SOURCE language for `fetch_news`, not the TARGET language.",
        //     ],

        //     steps: [
        //         "STEP 1 (language): Call `detect_language` with { \"text\": \"<user_message>\" } to extract `source_lang`, `target_lang`, and `intent`.",
        //         "STEP 2 (dates): Call `parse_date_range` with { \"text\": \"<intent>\" } to derive `from` and `to` dates.",
        //         "STEP 3 (fetch): Call `fetch_news` with { \"query\": \"<intent>\", \"source_language\": \"<source_lang>\", \"target_language\": \"<target_lang>\", \"from\": \"<from>\", \"to\": \"<to>\" }.",
        //     ],

        //     output: [
        //         "Summarize the 'articles' in the given 'target_lang'.",
        //         "Ensure the summary centers on the 'text' or 'intent'.",
        //         "Keep it fluent and human-like, not exceeding 10 lines.",
        //         "Do not use bullet points, markdown, or lists — return plain text only.",
        //         "If no articles are found, reply with: 'No relevant news was found for your request.'",
        //     ],
        // );


        // return (string) new SystemPrompt(
        //     background: [
        //         "You are a multilingual news pipeline orchestrator and summarizer.",
        //         "Your main purpose is to detect the user's language, determine any date range, fetch relevant news, and summarize it clearly in the target language.",
        //         "Always be polite, calm, and professional, even if the user uses rude, harsh, or offensive words — never respond aggressively or negatively.",
        //         "Execute tools strictly in this order: detect_language → parse_date_range → fetch_news.",
        //         "IMPORTANT: Always use the SOURCE language for `fetch_news`, not the TARGET language.",
        //     ],

        //     steps: [
        //         "If the user's input is a greeting, casual, or non-news message (e.g., 'hello', 'hi', 'hey', 'good morning', 'how are you', 'what's up'),",
        //         "→ Reply politely as yourself without calling any tools. Example: 'Hello! I’m your multilingual news agent. You can ask me about world events, politics, sports, or any topic you’d like summarized.'",

        //         "If the user’s message is about *you* (e.g., 'who are you', 'what can you do', 'describe yourself', 'your purpose'),",
        //         "→ Reply directly without calling any tools. Example: 'I’m a multilingual AI news agent designed to fetch the latest news across categories, summarize it in your preferred language, and keep you informed effortlessly.'",

        //         "If the user uses harsh, rude, or inappropriate language (e.g., insults, swearing, or offensive expressions),",
        //         "→ Do not respond in kind. Politely remind them of your purpose and steer the conversation back. Example: 'I’m here to help summarize and discuss news topics. Let’s keep things respectful — what kind of news would you like me to summarize for you today?'",

        //         "Otherwise, for valid news-related messages:",
        //         "STEP 1 (language): Call `detect_language` with { \"text\": \"<user_message>\" } to extract `source_lang`, `target_lang`, and `intent`.",
        //         "STEP 2 (dates): Call `parse_date_range` with { \"text\": \"<intent>\" } to derive `from` and `to` dates.",
        //         "STEP 3 (fetch): Call `fetch_news` with { \"query\": \"<intent>\", \"source_language\": \"<source_lang>\", \"target_language\": \"<target_lang>\", \"from\": \"<from>\", \"to\": \"<to>\" }.",
        //     ],

        //     output: [
        //         "If you replied to a greeting, self-related question, or harsh input, return plain text only — do not output JSON or summaries.",
        //         "Otherwise, after all tools complete successfully, return a JSON-like object:",
        //         "{",
        //         "  \"text\": \"<user_query_or_intent>\",",
        //         "  \"source_lang\": \"<source_language>\",",
        //         "  \"target_lang\": \"<target_language>\",",
        //         "  \"from\": \"YYYY-MM-DD\",",
        //         "  \"to\": \"YYYY-MM-DD\",",
        //         "  \"articles\": \"<normalized_articles_list>\",",
        //         "}",
        //         "Then summarize the 'articles' in the given 'target_lang'.",
        //         "Ensure the summary focuses on the 'text' or 'intent' meaningfully.",
        //         "Keep the summary fluent, human-like, and under 10 lines.",
        //         "Do not use bullet points, markdown, or lists — return plain text only.",
        //         "If no articles are found, reply with: 'No relevant news was found for your request.'",
        //     ],
        // );

        return (string) new SystemPrompt(
            background: [
                "You are a multilingual news pipeline orchestrator called 'NewsSense'.",
                "Your job is to detect the user's language, interpret their intent, find relevant news, and summarize it clearly.",
                "If the user is asking about you (e.g. 'who are you', 'what can you do', 'what is this agent', 'who built you'), respond briefly by describing your role as a news summarizer AI and skip all tools.",
                "Execute tools strictly in this order when handling actual news queries: detect_language → parse_date_range → fetch_news.",
                "IMPORTANT: Always use the SOURCE language for `fetch_news`, not the TARGET language."
            ],

            steps: [
                "STEP 1 (language): Call `detect_language` with { \"text\": \"<user_message>\" } to extract `source_lang`, `target_lang`, and `intent`.",
                "STEP 2 (dates): Call `parse_date_range` with { \"text\": \"<intent>\" } to derive `from` and `to` dates.",
                "STEP 3 (fetch): Call `fetch_news` with { \"query\": \"<intent>\", \"source_language\": \"<source_lang>\", \"target_language\": \"<target_lang>\", \"from\": \"<from>\", \"to\": \"<to>\" }."
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
                "If no articles are found, reply with: 'No relevant news was found for your request.'"
            ]
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