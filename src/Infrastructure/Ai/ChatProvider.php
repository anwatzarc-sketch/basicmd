<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Ai;

use Aster\Infrastructure\Support\Logger;

/**
 * Minimal client for an OpenAI-compatible /chat/completions endpoint.
 *
 * Deliberately written against that shape rather than one vendor's SDK,
 * because it is the shape nearly every hosted model speaks: Groq, OpenRouter,
 * Together, DeepInfra, Gemini's compatibility endpoint and a self-hosted
 * Ollama all accept the same request body. Switching provider is then an .env
 * change (AI_BASE_URL + AI_MODEL + AI_API_KEY), not a code change.
 *
 * cURL directly, no SDK: this application ships its vendor directory to shared
 * hosting by upload, so every dependency avoided is one fewer thing that can
 * break a deploy.
 *
 * PHI SAFETY: a visitor may type symptoms into the chat. Nothing in this class
 * ever logs the message content - only status codes, durations and error
 * classes. The same rule as Logger's: the transcript is not ours to keep.
 */
final class ChatProvider
{
    public function __construct(
        private readonly Logger $logger,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeoutSeconds = 20,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->model !== '';
    }

    /**
     * One completion, or null when the provider is unreachable or unhappy.
     *
     * Returning null rather than throwing is deliberate: a chat widget that
     * cannot reach its model must degrade to "I could not answer, here is the
     * phone number", never to a 500 on the page it is embedded in.
     *
     * @param list<array{role:string, content:string}> $messages
     */
    public function complete(array $messages, float $temperature = 0.2, int $maxTokens = 500): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'stream'      => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            $this->logger->error('AI request could not be encoded');

            return null;
        }

        $headers = ['Content-Type: application/json'];

        // Some gateways (a local Ollama, for instance) need no key at all.
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $started = microtime(true);
        $handle  = curl_init(rtrim($this->baseUrl, '/') . '/chat/completions');

        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $body   = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($handle);
        curl_close($handle);

        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($body === false || $status < 200 || $status >= 300) {
            $this->logger->warning('AI request failed', [
                'status' => $status,
                'ms'     => $ms,
                // The provider's own error text is safe: it describes the
                // request envelope, never the visitor's words.
                'error'  => $error !== '' ? $error : mb_substr((string) $body, 0, 200),
            ]);

            return null;
        }

        $decoded = json_decode((string) $body, true);
        $answer  = $decoded['choices'][0]['message']['content'] ?? null;

        if (!is_string($answer) || trim($answer) === '') {
            $this->logger->warning('AI response had no usable content', ['status' => $status, 'ms' => $ms]);

            return null;
        }

        $this->logger->info('AI reply delivered', [
            'ms'     => $ms,
            'tokens' => $decoded['usage']['total_tokens'] ?? null,
        ]);

        return trim($answer);
    }
}
