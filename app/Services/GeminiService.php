<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

    public function __construct()
    {
        $this->apiKey = config('connected.ai.gemini_api_key');
    }

    public function summarize(string $text): string
    {
        if (empty($this->apiKey)) {
            Log::warning('Gemini API key not configured. Returning mock summary.');
            return "This is a simulated AI summary because the API key is missing. Input text length: " . strlen($text);
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "Summarize the following clinical notes into a concise SBAR format:\n\n" . $text]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text') ?? 'No summary generated.';
            }

            Log::error('Gemini API Error: ' . $response->body());
            return "Error generating summary. Please try again later.";

        } catch (
Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            return "System error during summarization.";
        }
    }

    public function analyzeRisk(array $data): array
    {
        // Placeholder for risk analysis logic
        // In a real scenario, this would send JSON data to Gemini and ask for risk flags
        return [
            'risk_level' => 'moderate', // simulated
            'flags' => ['Simulated Risk 1', 'Simulated Risk 2']
        ];
    }
}
