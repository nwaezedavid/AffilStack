<?php

namespace App\Services\AI;

interface AIProvider
{
    /**
     * Generate freeform text from a system + user prompt pair.
     */
    public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string;

    /**
     * Generate a structured JSON payload. The system prompt should describe
     * the expected shape; the returned array is the decoded JSON.
     *
     * @return array<string, mixed>
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array;

    /**
     * Generate an image and return its URL (or a data: URI, depending on provider).
     */
    public function generateImage(string $prompt, array $options = []): string;
}
