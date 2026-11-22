<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomainSslMonitoringController extends Controller
{
    /**
     * Menampilkan hasil pemeriksaan domain dan SSL terbaru
     * untuk setiap website.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'domain_status' => [
                'sometimes',
                Rule::in([
                    'valid',
                    'expiring_soon',
                    'expired',
                    'not_found',
                    'unavailable',
                    'unsupported',
                    'error',
                ]),
            ],

            'ssl_status' => [
                'sometimes',
                Rule::in([
                    'valid',
                    'expiring_soon',
                    'expired',
                    'not_yet_valid',
                    'verification_failed',
                    'unavailable',
                    'unsupported',
                    'error',
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

        $query = Website::query()
            ->with([
                'opd' => function ($opdQuery) {
                    $opdQuery
                        ->withTrashed()
                        ->select('id', 'name', 'code');
                },

                'latestDomainRegistrationCheck',

                'latestSslCertificateCheck',
            ]);

        if (isset($filters['opd_id'])) {
            $query->where('opd_id', $filters['opd_id']);
        }

        if (isset($filters['website_id'])) {
            $query->where('id', $filters['website_id']);
        }

        if (isset($filters['domain_status'])) {
            $status = $filters['domain_status'];

            $query->whereHas(
                'latestDomainRegistrationCheck',
                fn (Builder $checkQuery) =>
                    $checkQuery->where('status', $status)
            );
        }

        if (isset($filters['ssl_status'])) {
            $status = $filters['ssl_status'];

            $query->whereHas(
                'latestSslCertificateCheck',
                fn (Builder $checkQuery) =>
                    $checkQuery->where('status', $status)
            );
        }

        /*
         * Ringkasan berdasarkan website yang sesuai
         * dengan filter aktif.
         */
        $summaryQuery = clone $query;

        $summary = [
            'total_websites' => (clone $summaryQuery)->count(),

            'domain' => [
                'checked' => $this->countWithLatestCheck(
                    $summaryQuery,
                    'latestDomainRegistrationCheck'
                ),

                'not_checked' => $this->countWithoutLatestCheck(
                    $summaryQuery,
                    'latestDomainRegistrationCheck'
                ),

                'valid' => $this->countByStatus(
                    $summaryQuery,
                    'latestDomainRegistrationCheck',
                    'valid'
                ),

                'expiring_soon' => $this->countByStatus(
                    $summaryQuery,
                    'latestDomainRegistrationCheck',
                    'expiring_soon'
                ),

                'expired' => $this->countByStatus(
                    $summaryQuery,
                    'latestDomainRegistrationCheck',
                    'expired'
                ),
            ],

            'ssl' => [
                'checked' => $this->countWithLatestCheck(
                    $summaryQuery,
                    'latestSslCertificateCheck'
                ),

                'not_checked' => $this->countWithoutLatestCheck(
                    $summaryQuery,
                    'latestSslCertificateCheck'
                ),

                'valid' => $this->countByStatus(
                    $summaryQuery,
                    'latestSslCertificateCheck',
                    'valid'
                ),

                'expiring_soon' => $this->countByStatus(
                    $summaryQuery,
                    'latestSslCertificateCheck',
                    'expiring_soon'
                ),

                'expired' => $this->countByStatus(
                    $summaryQuery,
                    'latestSslCertificateCheck',
                    'expired'
                ),

                'verification_failed' => $this->countByStatus(
                    $summaryQuery,
                    'latestSslCertificateCheck',
                    'verification_failed'
                ),
            ],
        ];

        $paginator = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15);

        $data = $paginator->getCollection()
            ->map(function (Website $website) {
                return [
                    'website' => [
                        'id' => $website->id,
                        'opd_id' => $website->opd_id,
                        'name' => $website->name,
                        'url' => $website->url,
                        'is_active' => $website->is_active,
                        'monitoring_enabled' => $website->monitoring_enabled,
                    ],

                    'opd' => [
                        'id' => $website->opd?->id,
                        'name' => $website->opd?->name
                            ?? 'OPD tidak tersedia',
                        'code' => $website->opd?->code,
                    ],

                    'domain_check' => $website
                        ->latestDomainRegistrationCheck,

                    'ssl_check' => $website
                        ->latestSslCertificateCheck,
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
     * Menampilkan riwayat pemeriksaan domain dan SSL
     * untuk satu website.
     */
    public function history(
        Request $request,
        Website $website
    ): JsonResponse {
        $filters = $request->validate([
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $perPage = $filters['per_page'] ?? 15;

        $website->load([
            'opd' => fn ($query) => $query
                ->withTrashed()
                ->select('id', 'name', 'code'),
        ]);

        $domainChecks = $website->domainRegistrationChecks()
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'domain_page');

        $sslChecks = $website->sslCertificateChecks()
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'ssl_page');

        return response()->json([
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'url' => $website->url,

                'opd' => [
                    'id' => $website->opd?->id,
                    'name' => $website->opd?->name
                        ?? 'OPD tidak tersedia',
                ],
            ],

            'domain_checks' => $domainChecks,

            'ssl_checks' => $sslChecks,
        ]);
    }

    /**
     * Menghitung website yang sudah memiliki pemeriksaan terbaru.
     */
    private function countWithLatestCheck(
        Builder $query,
        string $relation
    ): int {
        return (clone $query)
            ->whereHas($relation)
            ->count();
    }

    /**
     * Menghitung website yang belum memiliki pemeriksaan.
     */
    private function countWithoutLatestCheck(
        Builder $query,
        string $relation
    ): int {
        return (clone $query)
            ->whereDoesntHave($relation)
            ->count();
    }

    /**
     * Menghitung hasil berdasarkan status pemeriksaan terbaru.
     */
    private function countByStatus(
        Builder $query,
        string $relation,
        string $status
    ): int {
        return (clone $query)
            ->whereHas(
                $relation,
                fn (Builder $checkQuery) =>
                    $checkQuery->where('status', $status)
            )
            ->count();
    }
}