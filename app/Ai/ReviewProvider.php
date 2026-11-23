<?php

namespace App\Ai;

interface ReviewProvider
{
    /** "demo" or "claude": stored on the analysis and shown in the UI. */
    public function mode(): string;

    /** @throws AiReviewFailed with a message that is safe to show the user */
    public function review(ReviewRequest $request): ReviewResult;
}
