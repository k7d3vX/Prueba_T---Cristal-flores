<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(AuditLogger $audit): View
    {
        return view('owner.audit-log', ['logs' => $audit->latest(200)]);
    }
}
