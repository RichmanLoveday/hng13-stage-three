<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Neuron\Agents\NewsAgent;
use NeuronAI\Chat\Messages\UserMessage;
use Str;
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
        $body = $request->all();

        // $validated = $request->validate([
        //     'text' => 'required|string|min:3|max:500',
        // ]);

        //? Validate minimal JSON-RPC
        $jsonrpc = $body['jsonrpc'] ?? null;
        $id = $body['id'] ?? null;
        $params = $body['params'] ?? [];
        $message = $params['message'] ?? null;

        // dd($jsonrpc, $id, $params, $message);

        if ($jsonrpc !== '2.0' || !$id || !$message) {
            return response()->json([
                "jsonrpc" => "2.0",
                "id" => $id ?? null,
                "error" => [
                    "code" => -32600,
                    "message" => "Invalid A2A JSON-RPC Request"
                ]
            ], 400);
        }

        //? Extract user text
        $text = $message['parts'][0]['text'] ?? '';

        try {
            //? call news agent response
            $agentResponse = $agent->chat(
                new UserMessage($text)
            )->getContent();

            //? Build A2A response
            $responseText = $agentResponse ?? "No response";

            $taskId       = $message["taskId"] ?? Str::uuid()->toString();
            $contextId    = Str::uuid()->toString();
            $messageId    = Str::uuid()->toString();
            $artifactMsg  = Str::uuid()->toString();
            $artifactTool = Str::uuid()->toString();


            return response()->json([
                "jsonrpc" => "2.0",
                "id" => $id,
                "result" => [
                    "id" => $taskId,
                    "contextId" => $contextId,
                    "status" => [
                        "state" => "completed",
                        "timestamp" => now()->toISOString(),
                        "message" => [
                            "messageId" => $messageId,
                            "role" => "agent",
                            "parts" => [
                                [
                                    "kind" => "text",
                                    "text" => $responseText
                                ]
                            ],
                            "kind" => "message"
                        ]
                    ],

                    "artifacts" => [
                        [
                            "artifactId" => $artifactMsg,
                            "name" => "newsAgentResponse",
                            "parts" => [
                                [
                                    "kind" => "text",
                                    "text" => $responseText
                                ]
                            ]
                        ],
                        [
                            "artifactId" => $artifactTool,
                            "name" => "ToolResults",
                            "parts" => [
                                [
                                    "kind" => "data",
                                    "data" => [
                                        "type" => "tool-result",
                                        "runId" => Str::uuid()->toString(),
                                        "from" => "AGENT",
                                        "payload" => [
                                            "args" => [
                                                "text" => $text
                                            ],
                                            "toolName" => "NewsAgent",
                                            "result" => [
                                                "success" => true,
                                                "responseLength" => strlen($responseText)
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],

                    "history" => [$message],
                    "kind" => "task"
                ]
            ]);
        } catch (Throwable $e) {
            Log::error('A2A NewsAgent failed', ['error' => $e->getMessage()]);

            return response()->json([
                "jsonrpc" => "2.0",
                "id" => $id,
                "error" => [
                    "code" => -32603,
                    "message" => "Internal error",
                    "data" => $e->getMessage()
                ]
            ], 500);
        }
    }
}