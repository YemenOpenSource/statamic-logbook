<?php

namespace EmranAlhaddad\StatamicLogbook\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Throwable;

class LogbookUtilityController
{
    public function __invoke(Request $request)
    {
        // default page: system
        return redirect()->to(cp_route('utilities.logbook.system'));
    }

    public function system(Request $request)
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_system_logs');

        if ($from = $request->get('from')) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('created_at', '<=', $to);
        if ($level = $request->get('level')) $q->where('level', $level);
        if ($channel = $request->get('channel')) $q->where('channel', $channel);

        if ($search = trim((string) $request->get('q'))) {
            $q->where('message', 'like', "%{$search}%");
        }

        $logs = $q->orderByDesc('id')->paginate(50)->withQueryString();

        $levels = DB::connection($conn)->table('logbook_system_logs')
            ->select('level')->distinct()->orderBy('level')->pluck('level')->all();

        $channels = DB::connection($conn)->table('logbook_system_logs')
            ->select('channel')->whereNotNull('channel')->distinct()->orderBy('channel')->pluck('channel')->all();

        $stats = $this->systemStats($conn);
        return view('statamic-logbook::cp.logbook.system', [
            'filters' => $request->all(),
            'logs' => $logs,
            'stats' => $stats,
            'levels' => $levels,
            'channels' => $channels,
        ]);
    }

    public function audit(Request $request)
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_audit_logs');

        if ($from = $request->get('from')) {
            $q->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        if ($action = $request->get('action')) {
            $q->where('action', $action);
        }

        if ($subject = $request->get('subject_type')) {
            $q->where('subject_type', $subject);
        }

        if ($user = $request->get('user_id')) {
            $q->where('user_id', $user);
        }

        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('subject_title', 'like', "%{$search}%")
                    ->orWhere('subject_handle', 'like', "%{$search}%");
            });
        }

        $logs = $q->orderByDesc('id')->paginate(50)->withQueryString();

        $actions = DB::connection($conn)->table('logbook_audit_logs')
            ->select('action')->distinct()->orderBy('action')->pluck('action')->all();

        $subjects = DB::connection($conn)->table('logbook_audit_logs')
            ->select('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')->all();

        $stats = $this->auditStats($conn);

        return view('statamic-logbook::cp.logbook.audit', [
            'filters' => $request->all(),
            'logs' => $logs,
            'stats' => $stats,
            'actions' => $actions,
            'subjects' => $subjects,
        ]);
    }

    public function exportSystemCsv(Request $request): StreamedResponse
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_system_logs');

        if ($from = $request->get('from')) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('created_at', '<=', $to);
        if ($level = $request->get('level')) $q->where('level', $level);
        if ($channel = $request->get('channel')) $q->where('channel', $channel);

        if ($search = trim((string) $request->get('q'))) {
            $q->where('message', 'like', "%{$search}%");
        }

        $filename = 'logbook_system_logs_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($q) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel (Arabic safe)
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, [
                'id',
                'created_at',
                'level',
                'channel',
                'message',
                'context',
                'request_id',
                'user_id',
                'ip',
                'method',
                'url',
                'user_agent'
            ]);

            $q->orderBy('id')->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->created_at,
                        $r->level,
                        $r->channel,
                        $r->message,
                        $r->context,
                        $r->request_id,
                        $r->user_id,
                        $r->ip,
                        $r->method,
                        $r->url,
                        $r->user_agent,
                    ]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }

    public function exportAuditCsv(Request $request): StreamedResponse
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_audit_logs');

        if ($from = $request->get('from')) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('created_at', '<=', $to);
        if ($action = $request->get('action')) $q->where('action', $action);
        if ($subject = $request->get('subject_type')) $q->where('subject_type', $subject);
        if ($user = $request->get('user_id')) $q->where('user_id', $user);

        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('subject_title', 'like', "%{$search}%")
                    ->orWhere('subject_handle', 'like', "%{$search}%");
            });
        }

        $filename = 'logbook_audit_logs_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($q) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel (Arabic safe)
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, [
                'id',
                'created_at',
                'action',
                'user_id',
                'user_email',
                'subject_type',
                'subject_id',
                'subject_handle',
                'subject_title',
                'changes',
                'meta',
                'ip',
                'user_agent'
            ]);

            $q->orderBy('id')->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->created_at,
                        $r->action,
                        $r->user_id,
                        $r->user_email,
                        $r->subject_type,
                        $r->subject_id,
                        $r->subject_handle,
                        $r->subject_title,
                        $r->changes,
                        $r->meta,
                        $r->ip,
                        $r->user_agent,
                    ]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }

    public function runPrune(Request $request): JsonResponse
    {
        return $this->runCommand('logbook:prune');
    }

    public function runFlushSpool(Request $request): JsonResponse
    {
        return $this->runCommand('logbook:flush-spool');
    }

    protected function runCommand(string $command): JsonResponse
    {
        try {
            $exitCode = Artisan::call($command);
            $output = trim((string) Artisan::output());

            if ($exitCode === 0) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Command completed successfully.',
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Command failed.',
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $output,
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'command' => $command,
                'output' => '',
            ], 500);
        }
    }

    protected function systemStats(string $conn): array
    {
        $now = now();
        $since24h = $now->copy()->subHours(24);
        $since7d  = $now->copy()->subDays(7);

        $base = DB::connection($conn)->table('logbook_system_logs');

        $total24h = (clone $base)->where('created_at', '>=', $since24h)->count();

        $errors24h = (clone $base)
            ->where('created_at', '>=', $since24h)
            ->whereIn('level', ['emergency', 'alert', 'critical', 'error'])
            ->count();

        $warnings24h = (clone $base)
            ->where('created_at', '>=', $since24h)
            ->where('level', 'warning')
            ->count();

        $topLevels7d = (clone $base)
            ->where('created_at', '>=', $since7d)
            ->select('level', DB::raw('COUNT(*) as c'))
            ->groupBy('level')
            ->orderByDesc('c')
            ->limit(5)
            ->get()
            ->map(fn($r) => ['level' => $r->level ?? 'unknown', 'count' => (int) $r->c])
            ->all();

        return [
            'total_24h' => (int) $total24h,
            'errors_24h' => (int) $errors24h,
            'warnings_24h' => (int) $warnings24h,
            'top_levels_7d' => $topLevels7d,
        ];
    }

    protected function auditStats(string $conn): array
    {
        $now = now();
        $since24h = $now->copy()->subHours(24);
        $since7d  = $now->copy()->subDays(7);

        $base = DB::connection($conn)->table('logbook_audit_logs');

        $total24h = (clone $base)->where('created_at', '>=', $since24h)->count();

        $topActions7d = (clone $base)
            ->where('created_at', '>=', $since7d)
            ->select('action', DB::raw('COUNT(*) as c'))
            ->groupBy('action')
            ->orderByDesc('c')
            ->limit(7)
            ->get()
            ->map(fn($r) => ['action' => $r->action ?? 'unknown', 'count' => (int) $r->c])
            ->all();

        $topUsers7d = (clone $base)
            ->where('created_at', '>=', $since7d)
            ->select(
                DB::raw('COALESCE(user_email, user_id, "unknown") as u'),
                DB::raw('COUNT(*) as c')
            )
            ->groupBy('u')
            ->orderByDesc('c')
            ->limit(7)
            ->get()
            ->map(fn($r) => ['user' => (string) $r->u, 'count' => (int) $r->c])
            ->all();

        return [
            'total_24h' => (int) $total24h,
            'top_actions_7d' => $topActions7d,
            'top_users_7d' => $topUsers7d,
        ];
    }
}
