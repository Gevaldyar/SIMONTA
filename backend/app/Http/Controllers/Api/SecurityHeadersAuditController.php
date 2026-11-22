<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityHeadersAuditController extends Controller
{
    /**
     * Menampilkan hasil audit HTTP Security Headers terbaru
     * untuk setiap website.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'audit_status' => [
                'sometimes',
                Rule::in([
                    'strong',
                    'moderate',
                    'needs_review',
                    'weak',
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
            ->whereNotNull(
                'scans.metadata->security_headers_audit'
            )

            // Ambil audit terakhir yang tersedia untuk setiap website.
            ->whereNotExists(function ($subquery) {
                $subquery->selectRaw('1')
                    ->from('scans as newer_scans')
                    ->whereColumn(
                        'newer_scans.website_id',
                        'scans.website_id'
                    )
                    ->whereNotNull(
                        'newer_scans.metadata->security_headers_audit'
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

        // Filter berdasarkan status audit.
        if (isset($filters['audit_status'])) {
            $query->where(
                'scans.metadata->security_headers_audit->status',
                $filters['audit_status']
            );
        }

        // Filter berdasarkan website.
        if (isset($filters['website_id'])) {
            $query->where(
                'scans.website_id',
                $filters['website_id']
            );
        }

        // Filter berdasarkan OPD.
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

        // Ringkasan dihitung dari audit terbaru
        // yang sesuai dengan filter.
        $summary = [
            'total_websites' => (clone $query)->count(),

            'strong' => $this->countByStatus($query, 'strong'),

            'moderate' => $this->countByStatus($query, 'moderate'),

            'needs_review' => $this->countByStatus($query, 'needs_review'),

            'weak' => $this->countByStatus($query, 'weak'),

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
                $metadata = $scan->metadata ?? [];
                $audit = $metadata['security_headers_audit'] ?? [];
                $website = $scan->website;

                return [
                    'scan_id' => $scan->id,

                    'website' => [
                        'id' => $website?->id,
                        'name' => $website?->name
                            ?? 'Website tidak tersedia',
                        'url' => $website?->url,
                    ],

                    'opd' => [
                        'id' => $website?->opd?->id,
                        'name' => $website?->opd?->name
                            ?? 'OPD tidak tersedia',
                        'code' => $website?->opd?->code,
                    ],

                    'scan_status' => $scan->status,

                    'http_status_code' => $scan->http_status_code,

                    'audit' => [
                        'version' => $audit['version'] ?? null,

                        'status' => $audit['status'] ?? 'unknown',

                        'score' => $audit['score'] ?? null,

                        'max_score' => $audit['max_score'] ?? 100,

                        'applicable_max_score' =>
                            $audit['applicable_max_score'] ?? null,

                        'finding_count' => $audit['finding_count'] ?? 0,

                        'headers_present' => $audit['headers_present'] ?? 0,

                        'headers_checked' => $audit['headers_checked'] ?? 0,

                        'findings' => $audit['findings'] ?? [],

                        'checked_at' => $audit['checked_at'] ?? null,
                    ],

                    // Nilai header mentah untuk kebutuhan detail audit.
                    'security_headers' => $metadata['security_headers'] ?? [],

                    // Informasi sertifikat TLS dari scan yang sama.
                    'tls_certificate' => $metadata['tls_certificate'] ?? null,

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
     * Menghitung jumlah audit berdasarkan status.
     */
    private function countByStatus(
        Builder $query,
        string $status
    ): int {
        return (clone $query)
            ->where(
                'scans.metadata->security_headers_audit->status',
                $status
            )
            ->count();
    }
}