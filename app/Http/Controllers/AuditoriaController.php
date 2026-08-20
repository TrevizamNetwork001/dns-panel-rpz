<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->input('q', ''));
        $bucketFilter = (string) $request->input('bucket', 'all');

        $logs = AuditLog::with(['user', 'empresa'])->orderByDesc('id')->limit(200)->get();

        $visibleLogs = $logs->filter(function (AuditLog $log) use ($query, $bucketFilter) {
            $bucket = AuditLog::bucket($log->action);

            if ($bucketFilter !== 'all' && $bucketFilter !== $bucket) {
                return false;
            }

            if ($query === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $log->user->name ?? 'sistema',
                $log->action,
                $log->target_type ?? '',
                $log->ip_address ?? '',
                $log->description ?? '',
                $log->empresa->nome ?? '',
            ]));

            return str_contains($haystack, strtolower($query));
        })->values();

        $visibleCount = $visibleLogs->count();
        $recentCount = $visibleLogs->filter(fn (AuditLog $log) => $log->created_at->gte(now()->subHours(24)))->count();
        $authFailureCount = $visibleLogs->filter(fn (AuditLog $log) => str_contains($log->action, 'login_failed'))->count();
        $destructiveCount = $visibleLogs->filter(fn (AuditLog $log) => AuditLog::bucket($log->action) === 'warning')->count();

        return view('auditoria.index', compact('visibleLogs', 'visibleCount', 'recentCount', 'authFailureCount', 'destructiveCount', 'query', 'bucketFilter'));
    }
}
