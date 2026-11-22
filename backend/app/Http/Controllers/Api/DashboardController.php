<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use App\Models\Scan;
use App\Models\Technology;
use App\Models\TechnologySignature;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Ringkasan statistik SIMONTA.
     */
    public function summary(): JsonResponse
    {
        $stats = [
            'total_opds' => Opd::count(),

            'active_opds' => Opd::where(
                'is_active',
                true
            )->count(),

            'total_websites' => Website::count(),

            'active_websites' => Website::where(
                'is_active',
                true
            )->count(),

            'monitored_websites' => Website::query()
    ->where('is_active', true)
    ->where('monitoring_enabled', true)
    ->whereHas('opd', function ($query) {
        $query->where('is_active', true);
    })
    ->count(),

            'queued_scans' => Scan::where(
                'status',
                'queued'
            )->count(),

            'running_scans' => Scan::where(
                'status',
                'running'
            )->count(),

            'completed_scans' => Scan::where(
                'status',
                'completed'
            )->count(),

            'failed_scans' => Scan::where(
                'status',
                'failed'
            )->count(),

            'total_technologies' => Technology::where(
                'is_active',
                true
            )->count(),

            'total_signatures' => TechnologySignature::where(
                'is_active',
                true
            )->count(),
        ];

        $latestSuccessfulScan = Scan::query()
    ->where('status', 'completed')
    ->whereBetween('http_status_code', [200, 399])
    ->max('completed_at');

        return response()->json([
            'data' => [
                'statistics' => $stats,
                'latest_successful_scan_at' => $latestSuccessfulScan,
            ],
        ]);
    }

    /**
     * Distribusi teknologi berdasarkan hasil scan terbaru.
     */
    public function technologyDistribution(): JsonResponse
    {
        $latestCompletedUsage = DB::table('scan_technologies as st')
            ->join('scans as s', 's.id', '=', 'st.scan_id')
            ->join('websites as w', 'w.id', '=', 's.website_id')
            ->join('opds as o', 'o.id', '=', 'w.opd_id')
            ->where('s.status', 'completed')
            ->where('w.is_active', true)
            ->whereNull('w.deleted_at')
            ->where('o.is_active', true)
            ->whereNull('o.deleted_at')
            ->whereRaw(
                's.id = (
                    SELECT MAX(s2.id)
                    FROM scans AS s2
                    WHERE s2.website_id = s.website_id
                    AND s2.status = ?
                )',
                ['completed']
            )
            ->groupBy('st.technology_id')
            ->selectRaw(
                'st.technology_id,
                COUNT(DISTINCT s.website_id) AS websites_count'
            );

        $distribution = Technology::query()
            ->joinSub(
                $latestCompletedUsage,
                'usage',
                function ($join) {
                    $join->on(
                        'usage.technology_id',
                        '=',
                        'technologies.id'
                    );
                }
            )
            ->where('technologies.is_active', true)
            ->select('technologies.category')
            ->selectRaw(
                'COUNT(DISTINCT technologies.id) AS technologies_count'
            )
            ->selectRaw(
                'SUM(usage.websites_count) AS technology_website_matches'
            )
            ->groupBy('technologies.category')
            ->orderBy('technologies.category')
            ->get();

        return response()->json([
            'data' => $distribution,
        ]);
    }

    /**
     * Menampilkan pemeriksaan terbaru.
     */
    public function recentScans(): JsonResponse
    {
        $scans = Scan::query()
            ->with([
                'website' => function ($query) {
                    $query->withTrashed()
                        ->with([
                            'opd' => fn ($q) => $q->withTrashed(),
                        ]);
                },

                'scanTechnologies.technology',
            ])
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $scans,
        ]);
    }

/**
 * Menampilkan kesehatan website OPD berdasarkan hasil scan.
 *
 * Status:
 * - online: HTTP 200-399
 * - degraded: HTTP 400-499
 * - down: HTTP 500-599 atau scan gagal
 * - checking: scan queued atau running
 * - unknown: belum ada hasil scan yang dapat diklasifikasikan
 */
public function websiteHealth(): JsonResponse
{
    $now = now();

    $cutoff24h = $now->copy()->subDay();
    $cutoff7d = $now->copy()->subDays(7);

    // Hanya website dan OPD yang masih aktif.
    $websites = Website::query()
        ->with('opd:id,name')
        ->where('is_active', true)
        ->whereHas('opd', function ($query) {
            $query->where('is_active', true);
        })
        ->orderBy('name')
        ->get([
            'id',
            'opd_id',
            'name',
            'url',
            'monitoring_enabled',
            'monitoring_interval_minutes',
            'next_scan_at',
        ]);

    $websiteIds = $websites->modelKeys();

    $summary = [
        'total_websites' => $websites->count(),
        'online' => 0,
        'degraded' => 0,
        'down' => 0,
        'checking' => 0,
        'unknown' => 0,
        'uptime_24h_percent' => null,
        'uptime_7d_percent' => null,
        'average_response_time_7d_ms' => null,
        'checks_24h' => 0,
        'successful_checks_24h' => 0,
        'checks_7d' => 0,
        'successful_checks_7d' => 0,
    ];

    // Tidak ada website aktif.
    if ($websites->isEmpty()) {
        return response()->json([
            'data' => [
                'summary' => $summary,
                'websites' => [],
            ],
        ]);
    }

    // Ambil waktu pemeriksaan terbaru untuk setiap website.
$latestScanTimes = Scan::query()
    ->select('website_id')
    ->selectRaw('MAX(created_at) AS latest_created_at')
    ->whereIn('website_id', $websiteIds)
    ->groupBy('website_id');

// Ambil scan yang memiliki waktu terbaru.
// Jika timestamp sama, gunakan ID terbesar sebagai penentu.
$latestScans = Scan::query()
    ->joinSub(
        $latestScanTimes,
        'latest_scan_times',
        function ($join) {
            $join->on(
                'scans.website_id',
                '=',
                'latest_scan_times.website_id'
            )->on(
                'scans.created_at',
                '=',
                'latest_scan_times.latest_created_at'
            );
        }
    )
    ->select('scans.*')
    ->orderByDesc('scans.id')
    ->get()
    ->unique('website_id')
    ->keyBy('website_id');

    /*
     * Statistik uptime memakai scan terminal:
     * completed dan failed.
     *
     * queued/running tidak dihitung sebagai downtime
     * sebelum hasil pemeriksaan tersedia.
     */
    $scanMetrics = DB::table('scans')
        ->whereIn('website_id', $websiteIds)
        ->where('created_at', '>=', $cutoff7d)
        ->whereIn('status', ['completed', 'failed'])
        ->select('website_id')
        ->selectRaw('COUNT(*) AS checks_7d')
        ->selectRaw(
            "SUM(
                CASE
                    WHEN created_at >= ?
                    THEN 1 ELSE 0
                END
            ) AS checks_24h",
            [$cutoff24h]
        )
        ->selectRaw(
            "SUM(
                CASE
                    WHEN status = 'completed'
                    AND http_status_code >= 200
                    AND http_status_code < 400
                    THEN 1 ELSE 0
                END
            ) AS successful_checks_7d"
        )
        ->selectRaw(
            "SUM(
                CASE
                    WHEN created_at >= ?
                    AND status = 'completed'
                    AND http_status_code >= 200
                    AND http_status_code < 400
                    THEN 1 ELSE 0
                END
            ) AS successful_checks_24h",
            [$cutoff24h]
        )
        ->selectRaw(
            "SUM(
                CASE
                    WHEN status = 'completed'
                    AND response_time_ms IS NOT NULL
                    THEN response_time_ms
                    ELSE 0
                END
            ) AS response_time_sum_7d"
        )
        ->selectRaw(
            "SUM(
                CASE
                    WHEN status = 'completed'
                    AND response_time_ms IS NOT NULL
                    THEN 1 ELSE 0
                END
            ) AS response_time_count_7d"
        )
        ->groupBy('website_id')
        ->get()
        ->keyBy('website_id');

    $websiteResults = [];

    $totalResponseTime = 0;
    $totalResponseCount = 0;

    foreach ($websites as $website) {
        $latestScan = $latestScans->get($website->id);
        $metrics = $scanMetrics->get($website->id);

        $healthStatus = $this->determineWebsiteHealth(
            $latestScan
        );

        $summary[$healthStatus]++;

        $checks24h = (int) ($metrics->checks_24h ?? 0);
        $success24h = (int) ($metrics->successful_checks_24h ?? 0);

        $checks7d = (int) ($metrics->checks_7d ?? 0);
        $success7d = (int) ($metrics->successful_checks_7d ?? 0);

        $responseSum = (int) ($metrics->response_time_sum_7d ?? 0);
        $responseCount = (int) ($metrics->response_time_count_7d ?? 0);

        $averageResponseTime = $responseCount > 0
            ? round($responseSum / $responseCount, 2)
            : null;

        // Akumulasi statistik keseluruhan.
        $summary['checks_24h'] += $checks24h;
        $summary['successful_checks_24h'] += $success24h;

        $summary['checks_7d'] += $checks7d;
        $summary['successful_checks_7d'] += $success7d;

        $totalResponseTime += $responseSum;
        $totalResponseCount += $responseCount;

        $websiteResults[] = [
            'id' => $website->id,
            'name' => $website->name,
            'url' => $website->url,

            'opd' => [
                'id' => $website->opd?->id,
                'name' => $website->opd?->name,
            ],

            'monitoring_enabled' => $website->monitoring_enabled,
            'monitoring_interval_minutes' =>
                $website->monitoring_interval_minutes,

            'next_scan_at' => $website->next_scan_at,

            'health_status' => $healthStatus,

            'last_scan' => $latestScan ? [
                'id' => $latestScan->id,
                'status' => $latestScan->status,
                'http_status_code' => $latestScan->http_status_code,
                'response_time_ms' => $latestScan->response_time_ms,
                'created_at' => $latestScan->created_at,
                'started_at' => $latestScan->started_at,
                'completed_at' => $latestScan->completed_at,
            ] : null,

            'checks_24h' => $checks24h,
            'successful_checks_24h' => $success24h,

            'checks_7d' => $checks7d,
            'successful_checks_7d' => $success7d,

            'uptime_24h_percent' => $this->calculateUptime(
                $success24h,
                $checks24h
            ),

            'uptime_7d_percent' => $this->calculateUptime(
                $success7d,
                $checks7d
            ),

            'average_response_time_7d_ms' => $averageResponseTime,
        ];
    }

    $summary['uptime_24h_percent'] = $this->calculateUptime(
        $summary['successful_checks_24h'],
        $summary['checks_24h']
    );

    $summary['uptime_7d_percent'] = $this->calculateUptime(
        $summary['successful_checks_7d'],
        $summary['checks_7d']
    );

    $summary['average_response_time_7d_ms'] =
        $totalResponseCount > 0
            ? round($totalResponseTime / $totalResponseCount, 2)
            : null;

    return response()->json([
        'data' => [
            'summary' => $summary,
            'websites' => $websiteResults,
        ],
    ]);
}

/**
 * Menentukan status kesehatan berdasarkan scan terbaru.
 */
private function determineWebsiteHealth(?object $scan): string
{
    if (!$scan) {
        return 'unknown';
    }

    if (in_array($scan->status, ['queued', 'running'], true)) {
        return 'checking';
    }

    if ($scan->status === 'failed') {
        return 'down';
    }

    if ($scan->status !== 'completed') {
        return 'unknown';
    }

    $httpStatus = $scan->http_status_code;

    if ($httpStatus >= 200 && $httpStatus < 400) {
        return 'online';
    }

    if ($httpStatus >= 400 && $httpStatus < 500) {
        return 'degraded';
    }

    if ($httpStatus >= 500 && $httpStatus < 600) {
        return 'down';
    }

    return 'unknown';
}

/**
 * Menghitung uptime dalam persentase.
 *
 * Mengembalikan null jika belum ada pemeriksaan terminal.
 */
private function calculateUptime(
    int $successfulChecks,
    int $totalChecks
): ?float {
    if ($totalChecks === 0) {
        return null;
    }

    return round(
        ($successfulChecks / $totalChecks) * 100,
        2
    );
}

}
