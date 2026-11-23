<?php

namespace App\Policies;

use App\Models\Analysis;
use App\Models\User;

/** An analysis and everything in it belongs to exactly one user. */
class AnalysisPolicy
{
    public function view(User $user, Analysis $analysis): bool
    {
        return $analysis->user_id === $user->id;
    }

    public function retry(User $user, Analysis $analysis): bool
    {
        return $analysis->user_id === $user->id && $analysis->status === Analysis::FAILED && ! $analysis->is_demo;
    }

    public function delete(User $user, Analysis $analysis): bool
    {
        return $analysis->user_id === $user->id;
    }
}
