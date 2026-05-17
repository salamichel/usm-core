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

    public function buildImagePrompt(string $title, string $content, string $stylePrompt, string $geminiModel = 'gemini-3-flash-preview', ?string $logoPath = null): string
    {
        $textContent = strip_tags($content);
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim(mb_substr($textContent, 0, 2000));

        $logoInstruction = $logoPath ? "\nThe provided logo image defines the brand's visual identity. Incorporate its colors, style and mood into the image prompt." : '';
        $userMessage = "You are an expert at creating image generation prompts. Based on the following article, create a single concise image generation prompt (max 150 words, in English) that visually represents the article's theme.{$logoInstruction}\n\nStyle context: {$stylePrompt}\n\nArticle title: {$title}\n\nArticle content: {$textContent}\n\nRespond ONLY with the image generation prompt, nothing else.";

        $parts = [['text' => $userMessage]];

        if ($logoPath && file_exists($logoPath)) {
            $mimeType = mime_content_type($logoPath) ?: 'image/jpeg';
            // Gemini multimodal does not support SVG
            if ($mimeType !== 'image/svg+xml') {
                $parts[] = ['inlineData' => [
                    'mimeType' => $mimeType,
                    'data'     => base64_encode(file_get_contents($logoPath)),
                ]];
            }
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$this->apiKey}";
        $payload = json_encode([
            'contents'        => [['parts' => $parts]],
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

    public function generateExcerpt(string $title, string $content, string $stylePrompt, string $geminiModel = 'gemini-3-flash-preview'): string
    {
        $textContent = strip_tags($content);
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim(mb_substr($textContent, 0, 2000));

        $userMessage = "Tu es expert en rédaction web. À partir du contenu de cet article, écris une description courte (2-3 phrases, maximum 300 caractères) en français, adaptée au contexte fourni. Réponds uniquement avec le texte de la description, sans guillemets, sans markdown, sans introduction.\n\nContexte de style: {$stylePrompt}\n\nTitre de l'article: {$title}\n\nContenu: {$textContent}";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$this->apiKey}";
        $payload = json_encode([
            'contents'         => [['parts' => [['text' => $userMessage]]]],
            'generationConfig' => ['maxOutputTokens' => 150, 'temperature' => 0.6],
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
            Logger::errors()->error('GeminiService: generateExcerpt failed', ['title' => $title]);
            throw new \RuntimeException('Erreur lors de la connexion à l\'API Gemini.');
        }

        $data = json_decode($response, true);
        $excerpt = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$excerpt) {
            Logger::errors()->error('GeminiService: empty excerpt response', ['response' => $response]);
            throw new \RuntimeException('L\'API Gemini n\'a pas retourné de description.');
        }

        return trim($excerpt);
    }

    public function generateImage(string $imagePrompt, string $imagenModel = 'gemini-2.5-flash-image'): string
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
