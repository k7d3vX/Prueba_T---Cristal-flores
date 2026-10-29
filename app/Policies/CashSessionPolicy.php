<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

/** Autorización simple: la dueña ve todo; el cajero solo su caja. */
class CashSessionPolicy
{
    public function view(User $user, CashSession $session): bool
    {
        return $user->isOwner() || $session->cashier_id === $user->id;
    }

    /** Registrar ventas y salidas. */
    public function operate(User $user, CashSession $session): bool
    {
        return $this->view($user, $session);
    }

    /** El resumen del cierre es solo para la dueña (RN-5, AC-08). */
    public function summary(User $user, CashSession $session): bool
    {
        return $user->isOwner();
    }
}
