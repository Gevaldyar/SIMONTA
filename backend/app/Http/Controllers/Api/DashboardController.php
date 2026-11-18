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

            'monitored_websites' => Website::where(
                'is_active',
                true
            )->where(
                'monitoring_enabled',
                true
            )->count(),

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

        $latestCompletedScan = Scan::where(
            'status',
            'completed'
        )->max('completed_at');

        return response()->json([
            'data' => [
                'statistics' => $stats,
                'latest_completed_scan_at' => $latestCompletedScan,
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
}
