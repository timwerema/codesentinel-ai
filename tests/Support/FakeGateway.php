<?php

namespace Tests\Support;

use App\Ai\ClaudeGateway;

/** Stands in for the Claude API: records what was sent and returns a canned reply. */
class FakeGateway implements ClaudeGateway
{
    public ?string $system = null;

    public ?string $user = null;

    public int $calls = 0;

    public function __construct(private array|string $reply, private ?string $stopReason = 'end_turn') {}

    public function complete(string $system, string $user, array $schema): array
    {
        $this->calls++;
        $this->system = $system;
        $this->user = $user;

        return [
            'text' => is_string($this->reply) ? $this->reply : json_encode($this->reply),
            'stop_reason' => $this->stopReason, 'model' => 'claude-test', 'input_tokens' => 1000, 'output_tokens' => 500,
        ];
    }
}
