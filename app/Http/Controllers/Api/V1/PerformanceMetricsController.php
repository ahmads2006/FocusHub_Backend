<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PerformanceMetric;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceMetricsController extends Controller
{
    public function ingestWebVitals(Request $request)
    {
        $payload = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'ts' => ['nullable', 'numeric'],
            'vitals' => ['required', 'array'],
            'vitals.LCP' => ['nullable', 'numeric', 'min:0'],
            'vitals.INP' => ['nullable', 'numeric', 'min:0'],
            'vitals.CLS' => ['nullable', 'numeric', 'min:0'],
            'pass' => ['nullable', 'array'],
            'pass.LCP' => ['nullable', 'boolean'],
            'pass.INP' => ['nullable', 'boolean'],
            'pass.CLS' => ['nullable', 'boolean'],
            'budget' => ['nullable', 'array'],
        ]);

        $ts = isset($payload['ts'])
            ? Carbon::createFromTimestampMs((int) $payload['ts'])
            : now();

        PerformanceMetric::create([
            'metric_type' => 'web_vitals',
            'path' => $payload['path'],
            'ts' => $ts,
            'lcp' => $payload['vitals']['LCP'] ?? null,
            'inp' => $payload['vitals']['INP'] ?? null,
            'cls' => $payload['vitals']['CLS'] ?? null,
            'pass_lcp' => $payload['pass']['LCP'] ?? null,
            'pass_inp' => $payload['pass']['INP'] ?? null,
            'pass_cls' => $payload['pass']['CLS'] ?? null,
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'session_id' => ($cookie = $request->cookie(config('session.cookie'))) && is_string($cookie) ? hash('sha256', $cookie) : null,
            'meta' => [
                'budget' => $payload['budget'] ?? null,
            ],
        ]);

        return response()->json(['ok' => true], 202);
    }

    public function ingestPrefetch(Request $request)
    {
        $payload = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'ts' => ['nullable', 'numeric'],
            'metrics' => ['required', 'array'],
            'metrics.attempts' => ['nullable', 'integer', 'min:0'],
            'metrics.success' => ['nullable', 'integer', 'min:0'],
            'metrics.failed' => ['nullable', 'integer', 'min:0'],
            'metrics.skipped' => ['nullable', 'integer', 'min:0'],
        ]);

        $ts = isset($payload['ts'])
            ? Carbon::createFromTimestampMs((int) $payload['ts'])
            : now();

        PerformanceMetric::create([
            'metric_type' => 'prefetch',
            'path' => $payload['path'],
            'ts' => $ts,
            'prefetch_attempts' => $payload['metrics']['attempts'] ?? 0,
            'prefetch_success' => $payload['metrics']['success'] ?? 0,
            'prefetch_failed' => $payload['metrics']['failed'] ?? 0,
            'prefetch_skipped' => $payload['metrics']['skipped'] ?? 0,
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'session_id' => ($cookie = $request->cookie(config('session.cookie'))) && is_string($cookie) ? hash('sha256', $cookie) : null,
        ]);

        return response()->json(['ok' => true], 202);
    }

    public function summary(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $base = PerformanceMetric::query()
            ->where('metric_type', 'web_vitals')
            ->whereBetween('ts', [$from, $to]);

        $summary = (clone $base)
            ->selectRaw('AVG(lcp) as avg_lcp')
            ->selectRaw('AVG(inp) as avg_inp')
            ->selectRaw('AVG(cls) as avg_cls')
            ->selectRaw('COUNT(*) as total_samples')
            ->selectRaw('SUM(CASE WHEN pass_lcp = 0 THEN 1 ELSE 0 END) as failed_lcp')
            ->selectRaw('SUM(CASE WHEN pass_inp = 0 THEN 1 ELSE 0 END) as failed_inp')
            ->selectRaw('SUM(CASE WHEN pass_cls = 0 THEN 1 ELSE 0 END) as failed_cls')
            ->first();

        $prefetchSummary = PerformanceMetric::query()
            ->where('metric_type', 'prefetch')
            ->whereBetween('ts', [$from, $to])
            ->selectRaw('SUM(prefetch_attempts) as attempts')
            ->selectRaw('SUM(prefetch_success) as success')
            ->selectRaw('SUM(prefetch_failed) as failed')
            ->selectRaw('SUM(prefetch_skipped) as skipped')
            ->first();

        $total = max((int) ($summary->total_samples ?? 0), 1);

        return response()->json([
            'success' => true,
            'data' => [
                'range' => [
                    'from' => $from->toIso8601String(),
                    'to' => $to->toIso8601String(),
                ],
                'averages' => [
                    'lcp' => round((float) ($summary->avg_lcp ?? 0), 2),
                    'inp' => round((float) ($summary->avg_inp ?? 0), 2),
                    'cls' => round((float) ($summary->avg_cls ?? 0), 3),
                ],
                'budgets' => [
                    'lcp' => 2500,
                    'inp' => 200,
                    'cls' => 0.1,
                ],
                'failure_rate_percent' => [
                    'lcp' => round(((int) ($summary->failed_lcp ?? 0) / $total) * 100, 2),
                    'inp' => round(((int) ($summary->failed_inp ?? 0) / $total) * 100, 2),
                    'cls' => round(((int) ($summary->failed_cls ?? 0) / $total) * 100, 2),
                ],
                'total_samples' => (int) ($summary->total_samples ?? 0),
                'prefetch' => [
                    'attempts' => (int) ($prefetchSummary->attempts ?? 0),
                    'success' => (int) ($prefetchSummary->success ?? 0),
                    'failed' => (int) ($prefetchSummary->failed ?? 0),
                    'skipped' => (int) ($prefetchSummary->skipped ?? 0),
                ],
            ],
        ]);
    }

    public function worstPages(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $limit = max(1, min((int) $request->integer('limit', 10), 50));

        $rows = PerformanceMetric::query()
            ->where('metric_type', 'web_vitals')
            ->whereBetween('ts', [$from, $to])
            ->groupBy('path')
            ->select('path')
            ->selectRaw('COUNT(*) as samples')
            ->selectRaw('AVG(lcp) as avg_lcp')
            ->selectRaw('AVG(inp) as avg_inp')
            ->selectRaw('AVG(cls) as avg_cls')
            ->selectRaw('AVG(CASE WHEN pass_lcp = 1 THEN 1 ELSE 0 END) as pass_lcp_rate')
            ->orderByDesc('avg_lcp')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'path' => $row->path,
                    'samples' => (int) $row->samples,
                    'avg_lcp' => round((float) $row->avg_lcp, 2),
                    'avg_inp' => round((float) $row->avg_inp, 2),
                    'avg_cls' => round((float) $row->avg_cls, 3),
                    'pass_lcp_rate_percent' => round(((float) $row->pass_lcp_rate) * 100, 2),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    protected function resolveRange(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now();
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->subDays(7)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
