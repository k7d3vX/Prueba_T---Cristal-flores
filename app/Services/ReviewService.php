<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/** RN-21: la dueña marca un cierre como Revisado u Observado (con comentario). */
class ReviewService
{
    public function __construct(
        private CashSessionRepositoryInterface $sessions,
        private AuditLogger $audit,
    ) {}

    public function review(CashSession $session, User $owner, ReviewStatus $decision, ?string $note = null): CashSession
    {
        $note = trim((string) $note);

        if ($decision === ReviewStatus::Pending) {
            throw new DomainException('Elige Revisado u Observado.');
        }

        if ($decision === ReviewStatus::Flagged && $note === '') {
            throw new DomainException('Una observación necesita un comentario.');
        }

        return DB::transaction(function () use ($session, $owner, $decision, $note) {
            $locked = $this->sessions->lockForUpdate($session->id);

            if (! $locked->isClosed()) {
                throw new DomainException('Solo se revisan turnos cerrados.');
            }

            $reviewed = $this->sessions->update($locked, [
                'review_status' => $decision,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'review_note' => $note === '' ? null : $note,
            ]);

            $this->audit->record('revision', $decision->label().($note === '' ? '' : ': '.$note), $reviewed, $owner);

            return $reviewed;
        });
    }
}
