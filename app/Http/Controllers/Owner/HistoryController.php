<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoryRequest;
use App\Services\HistoryService;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(HistoryRequest $request, HistoryService $history): View
    {
        return view('owner.history', [
            'report' => $history->search($request->validated()),
            'filters' => $request->validated(),
        ]);
    }
}
