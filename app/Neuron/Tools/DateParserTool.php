<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class DateParserTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'parse_date_range',
            'Parses natural language date phrases (e.g. "today", "last week", "March 2025") into ISO 8601 date ranges.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'text',
                type: PropertyType::STRING,
                description: 'Text containing the date or date range phrase.',
                required: true,
            ),
        ];
    }

    public function __invoke(string $text): array
    {
        try {
            if (empty(trim($text))) {
                throw new \InvalidArgumentException('Text input is required.');
            }

            $now = CarbonImmutable::now();
            $from = $to = $now;
            $lower = mb_strtolower($text);

            if (preg_match('/\btoday\b/', $lower)) {
                $from = $to = $now;
            } elseif (preg_match('/\byesterday\b/', $lower)) {
                $from = $to = $now->subDay();
            } elseif (preg_match('/last\s+week/i', $lower)) {
                $from = $now->subWeek()->startOfWeek();
                $to   = $now->subWeek()->endOfWeek();
            } elseif (preg_match('/this\s+week/i', $lower)) {
                $from = $now->startOfWeek();
                $to   = $now->endOfWeek();
            } elseif (preg_match('/last\s+month/i', $lower)) {
                $from = $now->subMonth()->startOfMonth();
                $to   = $now->subMonth()->endOfMonth();
            } elseif (preg_match('/this\s+month/i', $lower)) {
                $from = $now->startOfMonth();
                $to   = $now->endOfMonth();
            } elseif (preg_match('/(\b\d{4}\b)/', $lower)) {
                // Handles year-based phrases like "March 2025"
                try {
                    $parsed = CarbonImmutable::parse($text);
                    $from = $parsed->startOfMonth();
                    $to = $parsed->endOfMonth();
                } catch (\Exception $e) {
                    // fallback to default week range
                    $from = $now->subDays(7);
                    $to = $now;
                }
            } else {
                // fallback default to past 7 days
                $from = $now->subDays(7);
                $to = $now;
            }

            return [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ];
        } catch (\Throwable $e) {
            Log::warning('DateParserTool error', [
                'input' => $text,
                'error' => $e->getMessage(),
            ]);

            // fallback to 7-day range
            $fallbackFrom = CarbonImmutable::now()->subDays(7);
            $fallbackTo = CarbonImmutable::now();

            return [
                'from' => $fallbackFrom->toDateString(),
                'to' => $fallbackTo->toDateString(),
            ];
        }
    }
}