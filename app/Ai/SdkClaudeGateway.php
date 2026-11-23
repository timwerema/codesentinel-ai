<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Claude through the official Anthropic PHP SDK. The key is read from server config only.
 * The SDK retries rate limits, overloads and connection errors itself (max_retries).
 */
class SdkClaudeGateway implements ClaudeGateway
{
    public function complete(string $system, string $user, array $schema): array
    {
        $c = config('codesentinel.ai');
        $client = new Client(apiKey: (string) $c['api_key']);

        try {
            $message = $client->beta->messages->create(
                maxTokens: $c['max_output_tokens'],
                messages: [['role' => 'user', 'content' => $user]],
                model: $c['model'],
                system: $system,
                thinking: ['type' => 'adaptive'],
                outputConfig: [
                    'effort' => $c['effort'],
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                // If the model declines for policy reasons, let the API retry on its default fallback.
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
                requestOptions: ['timeout' => (float) $c['timeout_seconds'], 'maxRetries' => $c['max_retries']],
            );
        } catch (AuthenticationException) {
            throw new AiReviewFailed('The AI provider rejected the API key. Check ANTHROPIC_API_KEY on the server.');
        } catch (RateLimitException) {
            throw new AiReviewFailed('The AI provider is rate limiting this account. Try again in a few minutes.');
        } catch (APIStatusException $e) {
            throw new AiReviewFailed('The AI provider returned an error (HTTP '.$e->status.').');
        } catch (APIConnectionException) {
            throw new AiReviewFailed('The AI provider could not be reached or took too long.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return [
            'text' => $text,
            'stop_reason' => $message->stopReason,
            'model' => (string) $message->model,
            'input_tokens' => (int) $message->usage->inputTokens,
            'output_tokens' => (int) $message->usage->outputTokens,
        ];
    }
}
