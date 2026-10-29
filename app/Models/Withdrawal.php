<?php

namespace App\Models;

use App\Enums\WithdrawalCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'category' => WithdrawalCategory::class,
            'voided_at' => 'datetime',
            'void_requested_at' => 'datetime',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** El cajero pidió anularla y la dueña aún no resolvió. */
    public function hasPendingVoidRequest(): bool
    {
        return $this->void_requested_at !== null && $this->voided_at === null;
    }
}
