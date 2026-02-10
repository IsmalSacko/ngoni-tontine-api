<?php


namespace App\Policies;

use App\Models\User;
use App\Models\Tontine;

class TontinePolicy
{
    public function update(User $user, Tontine $tontine)
    {
        return $user->id === $tontine->created_by;
    }

    public function delete(User $user, Tontine $tontine)
    {
        return $user->id === $tontine->created_by;
    }
}
