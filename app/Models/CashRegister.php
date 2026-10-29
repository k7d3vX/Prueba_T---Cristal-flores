<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una caja física de la tienda. Tiene muchas sesiones (turnos), pero solo una abierta a la vez. */
class CashRegister extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['default_float' => 'decimal:2'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }
}
