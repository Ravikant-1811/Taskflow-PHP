<?php

declare(strict_types=1);

function ai_is_configured(): bool
{
    $key = getenv('OPENAI_API_KEY');
    return is_string($key) && trim($key) !== '';
}

function ai_generate_text(string $systemPrompt, string $userPrompt): array
{
    $apiKey = getenv('OPENAI_API_KEY');
    if (!is_string($apiKey) || trim($apiKey) === '') {
        return ['ok' => false, 'text' => '', 'error' => 'OPENAI_API_KEY is not configured.'];
    }

    $config = require __DIR__ . '/config.php';
    $model = $config['openai_model'] ?? 'gpt-4.1-mini';

    $payload = [
        'model' => $model,
        'input' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 45,
    ]);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'text' => '', 'error' => 'Network error: ' . $error];
    }

    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'text' => '', 'error' => 'Empty response from AI service.'];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'text' => '', 'error' => 'Invalid AI response format.'];
    }

    if ($httpCode >= 400) {
        $apiError = $json['error']['message'] ?? ('HTTP ' . $httpCode);
        return ['ok' => false, 'text' => '', 'error' => 'AI API error: ' . $apiError];
    }

    $text = $json['output_text'] ?? '';
    if (!is_string($text) || trim($text) === '') {
        // Fallback extraction for alternative response shapes
        $text = '';
        if (isset($json['output']) && is_array($json['output'])) {
            foreach ($json['output'] as $item) {
                if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                    continue;
                }
                foreach ($item['content'] as $content) {
                    if (is_array($content) && isset($content['text']) && is_string($content['text'])) {
                        $text .= $content['text'] . "\n";
                    }
                }
            }
        }
    }

    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'text' => '', 'error' => 'AI returned no text output.'];
    }

    return ['ok' => true, 'text' => $text, 'error' => ''];
}
