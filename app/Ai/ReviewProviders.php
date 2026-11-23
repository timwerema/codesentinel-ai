<?php

namespace App\Ai;

/** Chooses the AI provider from config: Claude when a key is set, the labelled demo otherwise. */
class ReviewProviders
{
    public function __construct(private ClaudeGateway $gateway) {}

    public function current(): ?ReviewProvider
    {
        $mode = config('codesentinel.ai.mode', 'auto');

        return match (true) {
            $mode === 'off' => null,
            $mode === 'demo' => new DemoReviewProvider,
            filled(config('codesentinel.ai.api_key')) => new ClaudeReviewProvider($this->gateway),
            default => new DemoReviewProvider,
        };
    }
}
