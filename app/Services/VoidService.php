<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Models\Sale;
use App\Models\User;
use App\Models\Withdrawal;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Anulación: nada se borra (RN-22). La dueña anula con motivo; el cajero solo puede solicitarla (RN-23).
 */
class VoidService
{
    public function __construct(
        private SaleRepositoryInterface $sales,
        private WithdrawalRepositoryInterface $withdrawals,
        private CashSessionRepositoryInterface $sessions,
        private CashClosingService $closing,
        private AuditLogger $audit,
    ) {}

    public function voidSale(Sale $sale, User $owner, string $reason): Sale
    {
        return $this->void($sale, $this->sales, $owner, $reason, 'venta');
    }

    public function voidWithdrawal(Withdrawal $withdrawal, User $owner, string $reason): Withdrawal
    {
        return $this->void($withdrawal, $this->withdrawals, $owner, $reason, 'salida');
    }

    public function requestSaleVoid(Sale $sale, User $cashier, string $note): Sale
    {
        return $this->request($sale, $this->sales, $cashier, $note, 'venta');
    }

    public function requestWithdrawalVoid(Withdrawal $withdrawal, User $cashier, string $note): Withdrawal
    {
        return $this->request($withdrawal, $this->withdrawals, $cashier, $note, 'salida');
    }

    private function void(Sale|Withdrawal $movement, SaleRepositoryInterface|WithdrawalRepositoryInterface $repo, User $owner, string $reason, string $label): Sale|Withdrawal
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Anular exige un motivo.');
        }

        return DB::transaction(function () use ($movement, $repo, $owner, $reason, $label) {
            $session = $this->sessions->lockForUpdate($movement->cash_session_id);
            $current = $repo->findOrFail($movement->id);

            if ($current->isVoided()) {
                throw new DomainException('Ese registro ya está anulado.');
            }

            if ($session->isClosed() && $session->review_status === ReviewStatus::Reviewed) {
                throw new DomainException('El turno ya fue revisado: no se puede anular.');
            }

            // AR-03: anular una venta en efectivo no puede dejar el esperado en negativo (antes hay que anular la salida).
            if ($current instanceof Sale && $current->method === PaymentMethod::Cash
                && $this->closing->summary($session)->expectedCash - Money::toCents($current->amount) < 0) {
                throw new DomainException('No se puede anular: el efectivo esperado quedaría en negativo. Anula antes la salida.');
            }

            $voided = $repo->update($current, [
                'voided_at' => now(),
                'voided_by' => $owner->id,
                'void_reason' => $reason,
            ]);

            if ($session->isClosed()) {
                $this->closing->recalculate($session);
            }

            $this->audit->record('anulacion', 'Anuló '.$label.' de '.Money::format(Money::toCents($current->amount)).': '.$reason, $session, $owner);

            return $voided;
        });
    }

    private function request(Sale|Withdrawal $movement, SaleRepositoryInterface|WithdrawalRepositoryInterface $repo, User $cashier, string $note, string $label): Sale|Withdrawal
    {
        $note = trim($note);

        if ($note === '') {
            throw new DomainException('Cuéntale a la dueña qué se equivocó.');
        }

        $session = $this->sessions->findActiveForCashier($cashier->id);

        if ($session === null || ! $session->isOpen() || $session->id !== $movement->cash_session_id) {
            throw new DomainException('Solo puedes pedir la anulación de registros de tu turno abierto.');
        }

        if ($movement->isVoided() || $movement->void_requested_at !== null) {
            throw new DomainException('Ese registro ya está anulado o ya pediste anularlo.');
        }

        $updated = $repo->update($movement, ['void_requested_at' => now(), 'void_request_note' => $note]);
        $this->audit->record('anulacion.solicitada', 'Pidió anular '.$label.' de '.Money::format(Money::toCents($movement->amount)).': '.$note, $session, $cashier);

        return $updated;
    }
}
