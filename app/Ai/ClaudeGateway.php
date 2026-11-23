<?php

namespace App\Ai;

/** The one place that talks to the Claude API. Kept tiny so everything around it can be tested without a network. */
interface ClaudeGateway
{
    /**
     * @param  array<string, mixed>  $schema  JSON schema the reply must match
     * @return array{text: string, stop_reason: ?string, model: string, input_tokens: int, output_tokens: int}
     *
     * @throws AiReviewFailed
     */
    public function complete(string $system, string $user, array $schema): array;
}
