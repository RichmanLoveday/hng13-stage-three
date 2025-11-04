<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Neuron\Agents\NewsAgent;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;

class NewsAgentController extends Controller
{
    /**
     * Handle AI-powered news requests.
     *
     * Example request:
     * {
     *   "text": "Tell me about business news in Nigeria, translate to Igbo."
     * }
     */
    public function handle(Request $request, NewsAgent $agent)
    {
        // dd($request->all());
        $validated = $request->validate([
            'text' => 'required|string|min:3|max:500',
        ]);

        try {
            $response = $agent->chat(
                new UserMessage($validated['text'])
            );

            return response()->json([
                'success' => true,
                'message' => 'News summary generated successfully.',
                'data' => [
                    'input' => $validated['text'],
                    'summary' => $response->getContent(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('NewsAgentController failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process the news request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}