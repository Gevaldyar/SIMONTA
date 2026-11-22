<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityAssessmentController extends Controller
{
    /**
     * Menampilkan hasil Security Assessment terbaru
     * untuk setiap website.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'risk_status' => [
                'sometimes',
                Rule::in([
                    'low',
                    'review',
                    'high',
                    'unknown',
                ]),
            ],

            'opd_id' => [
                'sometimes',
                'integer',
                'exists:opds,id',
            ],

            'website_id' => [
                'sometimes',
                'integer',
                'exists:websites,id',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Scan::query()
            ->whereNotNull('scans.metadata->security_assessment')

            // Ambil satu assessment terakhir per website.
            ->whereNotExists(function ($subquery) {
    $subquery->selectRaw('1')
        ->from('scans as newer_scans')
        ->whereColumn(
            'newer_scans.website_id',
            'scans.website_id'
        )
        ->whereNotNull(
            'newer_scans.metadata->security_assessment'
        )
        ->where(function ($query) {
            $query->whereRaw(
                'COALESCE(newer_scans.completed_at, newer_scans.created_at)
                > COALESCE(scans.completed_at, scans.created_at)'
            )->orWhere(function ($tieBreaker) {
                $tieBreaker->whereRaw(
                    'COALESCE(newer_scans.completed_at, newer_scans.created_at)
                    = COALESCE(scans.completed_at, scans.created_at)'
                )->whereColumn(
                    'newer_scans.id',
                    '>',
                    'scans.id'
                );
            });
        });
})
            ->with([
                'website' => function ($websiteQuery) {
                    $websiteQuery
                        ->withTrashed()
                        ->select(
                            'id',
                            'opd_id',
                            'name',
                            'url'
                        )
                        ->with([
                            'opd' => fn ($opdQuery) => $opdQuery
                                ->withTrashed()
                                ->select('id', 'name', 'code'),
                        ]);
                },
            ]);

        if (isset($filters['risk_status'])) {
            $query->where(
                'scans.metadata->security_assessment->status',
                $filters['risk_status']
            );
        }

        if (isset($filters['website_id'])) {
            $query->where(
                'scans.website_id',
                $filters['website_id']
            );
        }

        if (isset($filters['opd_id'])) {
            $opdId = $filters['opd_id'];

            $query->whereHas(
                'website',
                function (Builder $websiteQuery) use ($opdId) {
                    $websiteQuery
                        ->withTrashed()
                        ->where('opd_id', $opdId);
                }
            );
        }

        // Ringkasan hanya berdasarkan assessment terbaru
        // yang sesuai dengan filter.
        $summary = [
            'total_websites' => (clone $query)->count(),

            'low' => $this->countByStatus($query, 'low'),

            'review' => $this->countByStatus($query, 'review'),

            'high' => $this->countByStatus($query, 'high'),

            'unknown' => $this->countByStatus($query, 'unknown'),
        ];

        $paginator = $query
            ->orderByRaw(
                'COALESCE(scans.completed_at, scans.created_at) DESC'
            )
            ->orderByDesc('scans.id')
            ->paginate($filters['per_page'] ?? 15);

        $data = $paginator->getCollection()
            ->map(function (Scan $scan) {
                $assessment = $scan->metadata['security_assessment'] ?? [];
                $website = $scan->website;

                return [
                    'scan_id' => $scan->id,

                    'website' => [
                        'id' => $website?->id,
                        'name' => $website?->name ?? 'Website tidak tersedia',
                        'url' => $website?->url,
                    ],

                    'opd' => [
                        'id' => $website?->opd?->id,
                        'name' => $website?->opd?->name
                            ?? 'OPD tidak tersedia',
                    ],

                    'scan_status' => $scan->status,
                    'http_status_code' => $scan->http_status_code,

                    'assessment' => [
                        'version' => $assessment['version'] ?? null,
                        'status' => $assessment['status'] ?? 'unknown',
                        'risk_score' => $assessment['risk_score'] ?? null,
                        'finding_count' => $assessment['finding_count'] ?? 0,
                        'findings' => $assessment['findings'] ?? [],
                        'reason' => $assessment['reason'] ?? null,
                        'html_analyzed' => $assessment['html_analyzed'] ?? false,
                        'assessed_at' => $assessment['assessed_at'] ?? null,
                    ],

                    'completed_at' => $scan->completed_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'summary' => $summary,
            'data' => $data,

            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Menghitung jumlah assessment berdasarkan status.
     */
    private function countByStatus(
        Builder $query,
        string $status
    ): int {
        return (clone $query)
            ->where(
                'scans.metadata->security_assessment->status',
                $status
            )
            ->count();
    }
}