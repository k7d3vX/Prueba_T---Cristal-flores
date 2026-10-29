<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewSessionRequest;
use App\Models\CashSession;
use App\Services\ReviewService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $service) {}

    public function store(ReviewSessionRequest $request, CashSession $cashSession): RedirectResponse
    {
        try {
            $this->service->review(
                $cashSession,
                $request->user(),
                ReviewStatus::from($request->validated('decision')),
                $request->validated('note'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', 'Cierre marcado como '.mb_strtolower($cashSession->fresh()->review_status->label()).'.');
    }
}
