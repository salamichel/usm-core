<?php
declare(strict_types=1);

namespace App\Services;

class GeminiService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = GEMINI_API_KEY;
    }

    public function buildImagePrompt(string $title, string $content, string $stylePrompt, string $geminiModel = 'gemini-2.0-flash'): string
    {
        $textContent = strip_tags($content);
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim(mb_substr($textContent, 0, 2000));

        $userMessage = "You are an expert at creating image generation prompts. Based on the following article, create a single concise image generation prompt (max 150 words, in English) that visually represents the article's theme.\n\nStyle context: {$stylePrompt}\n\nArticle title: {$title}\n\nArticle content: {$textContent}\n\nRespond ONLY with the image generation prompt, nothing else.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$this->apiKey}";
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $userMessage]]]],
            'generationConfig' => ['maxOutputTokens' => 200, 'temperature' => 0.7],
        ]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            Logger::errors()->error('GeminiService: buildImagePrompt failed', ['title' => $title]);
            throw new \RuntimeException('Erreur lors de la connexion à l\'API Gemini.');
        }

        $data = json_decode($response, true);
        $prompt = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$prompt) {
            Logger::errors()->error('GeminiService: empty prompt response', ['response' => $response]);
            throw new \RuntimeException('L\'API Gemini n\'a pas retourné de prompt.');
        }

        return trim($prompt);
    }

    public function generateImage(string $imagePrompt, string $imagenModel = 'imagen-3.0-generate-002'): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$imagenModel}:predict?key={$this->apiKey}";
        $payload = json_encode([
            'instances'  => [['prompt' => $imagePrompt]],
            'parameters' => ['sampleCount' => 1],
        ]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 60,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            Logger::errors()->error('GeminiService: generateImage failed', ['prompt' => $imagePrompt]);
            throw new \RuntimeException('Erreur lors de la connexion à l\'API Imagen.');
        }

        $data = json_decode($response, true);
        $b64 = $data['predictions'][0]['bytesBase64Encoded'] ?? null;
        if (!$b64) {
            Logger::errors()->error('GeminiService: no image in response', ['response' => mb_substr($response, 0, 500)]);
            throw new \RuntimeException('L\'API Imagen n\'a pas retourné d\'image.');
        }

        return base64_decode($b64);
    }
}
