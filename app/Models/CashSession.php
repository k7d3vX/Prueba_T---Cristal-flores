<?php

namespace App\Models;

use App\Enums\ClosingMode;
use App\Enums\ReviewStatus;
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
            'float_confirmed_at' => 'datetime',
            'float_disputed_at' => 'datetime',
            'review_status' => ReviewStatus::class,
            'count_breakdown' => 'array',
            'reviewed_at' => 'datetime',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === SessionStatus::Closed;
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

    /** Abierto = el cajero ya confirmó el fondo: solo entonces admite ventas, salidas y cierre. */
    public function isOpen(): bool
    {
        return $this->status === SessionStatus::Open;
    }

    /** La dueña lo abrió y el cajero aún no confirmó el fondo recibido. */
    public function isPending(): bool
    {
        return $this->status === SessionStatus::PendingConfirmation;
    }

    public function isBlind(): bool
    {
        return $this->closing_mode === ClosingMode::Blind;
    }
}
