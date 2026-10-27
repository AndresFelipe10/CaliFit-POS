<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AiQueryIntent;
use App\AI\Exceptions\AiProviderException;

interface AiProviderInterface
{
    /**
     * Extract structured intent and filters from user natural language query with optional conversation history.
     *
     * @param string $userQuery
     * @param array $history
     * @return AiQueryIntent
     * @throws AiProviderException
     */
    public function extractIntent(string $userQuery, array $history = []): AiQueryIntent;
}
