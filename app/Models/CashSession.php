<?php

namespace App\Models;

use App\Enums\ClosingMode;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'closing_mode' => ClosingMode::class,
            'status' => SessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /** Segundos entre apertura y cierre (null si sigue abierta). */
    public function workedSeconds(): ?int
    {
        if ($this->closed_at === null) {
            return null;
        }

        return max(0, $this->closed_at->getTimestamp() - $this->opened_at->getTimestamp());
    }

    public function isOpen(): bool
    {
        return $this->status === SessionStatus::Open;
    }

    public function isBlind(): bool
    {
        return $this->closing_mode === ClosingMode::Blind;
    }
}
