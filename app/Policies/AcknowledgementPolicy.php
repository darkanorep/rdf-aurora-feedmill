<?php

namespace App\Policies;

use App\Models\User;

class AcknowledgementPolicy
{

    public function acknowledge(User $authUser, User $evaluator)
    {
        if (!$evaluator->acknowledgement()->exists()) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'The evaluator does not have acknowledgement setting, contact the administrator.'
            );
        }
        return true;
    }
}
