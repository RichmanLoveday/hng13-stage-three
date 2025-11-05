# NewsAgent - AI News Summarizer

**NewsAgent** is an AI-powered news summarizer built with **Laravel/PHP** using the **Neuron AI** framework. It fetches real-time news, detects language and date ranges, and provides concise summaries.  

## Features
- Fetch news from [NewsAPI.org](https://newsapi.org/)
- Automatic language detection and translation
- Summarization of articles in natural language
- A2A integration for platforms like **Telex**

## Tools Used
- **LanguageDetectorTool** - Detects source and target languages
- **DateParserTool** - Parses date ranges from user queries
- **NewsFetcherTool** - Fetches news from the API
- **NewsAgent Class** - Orchestrates tools and generates responses

## Integration
- Supports JSON-RPC 2.0 A2A requests
- Artifacts and conversation history included for tracking

## Installation
1. Clone the repository
2. Run `composer install`
3. Configure `.env` with NewsAPI key
4. Start the Laravel server

## Usage
Send a POST request to `/api/a2a/news-agent` with JSON:
```json
{
  "jsonrpc": "2.0",
  "id": "req-001",
  "method": "message/send",
  "params": {
    "message": {
      "kind": "message",
      "role": "user",
      "parts": [
        {
          "kind": "text",
          "text": "What is happening in the senate house in the USA and please translate to German."
        }
      ],
      "messageId": "msg-001",
      "taskId": "task-001"
    }
  }
}

