<?php

namespace App\Policies;

use App\Enums\ProblemState;
use App\Models\Problem;
use App\Models\User;

class ProblemPolicy
{
    public function view(User $user, Problem $problem): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function confirm(User $user, Problem $problem): bool
    {
        if ($problem->state !== ProblemState::Added) {
            return false;
        }

        return $user->role->canConfirmProblem();
    }

    public function resolve(User $user, Problem $problem): bool
    {
        if ($problem->state !== ProblemState::Confirmed) {
            return false;
        }

        return $user->role->canResolveProblem();
    }

    public function unconfirm(User $user, Problem $problem): bool
    {
        if ($problem->state !== ProblemState::Confirmed) {
            return false;
        }

        return $user->role->canUnconfirmProblem();
    }

    public function unresolve(User $user, Problem $problem): bool
    {
        if ($problem->state !== ProblemState::Resolved) {
            return false;
        }

        return $user->role->canUnresolveProblem();
    }
}
