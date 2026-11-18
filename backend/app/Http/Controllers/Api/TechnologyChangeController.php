<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TechnologyChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TechnologyChangeController extends Controller
{
    /**
     * Memvalidasi filter daftar dan ringkasan perubahan.
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
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

            'change_type' => [
                'sometimes',
                'string',
                Rule::in([
                    'added',
                    'removed',
                    'version_changed',
                ]),
            ],

            'date_from' => [
                'sometimes',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);
    }

    /**
     * Membuat query dasar dan menerapkan filter.
     */
    private function applyFilters(
        Builder $query,
        array $filters
    ): Builder {
        if (isset($filters['opd_id'])) {
            $query->whereHas(
                'scan.website',
                function ($websiteQuery) use ($filters) {
                    $websiteQuery->withTrashed()->where(
                        'opd_id',
                        $filters['opd_id']
                    );
                }
            );
        }

        if (isset($filters['website_id'])) {
            $query->whereHas(
                'scan',
                function ($scanQuery) use ($filters) {
                    $scanQuery->where(
                        'website_id',
                        $filters['website_id']
                    );
                }
            );
        }

        if (isset($filters['change_type'])) {
            $query->where(
                'change_type',
                $filters['change_type']
            );
        }

        // Tanggal mengacu pada waktu perubahan dicatat.
        if (isset($filters['date_from'])) {
            $query->whereDate(
                'technology_changes.created_at',
                '>=',
                $filters['date_from']
            );
        }

        if (isset($filters['date_to'])) {
            $query->whereDate(
                'technology_changes.created_at',
                '<=',
                $filters['date_to']
            );
        }

        return $query;
    }

    /**
     * Relasi yang disertakan pada respons perubahan.
     */
    private function relationships(): array
    {
        return [
            'technology',

            'scan.website' => function ($query) {
                $query->withTrashed()
                    ->with([
                        'opd' => fn ($opdQuery) =>
                            $opdQuery->withTrashed(),
                    ]);
            },

            'previousScan',
        ];
    }

    /**
     * GET /api/technology-changes
     *
     * Menampilkan daftar perubahan teknologi.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $query = $this->applyFilters(
            TechnologyChange::query()
                ->with($this->relationships()),
            $filters
        );

        $changes = $query
            ->orderByDesc('technology_changes.created_at')
            ->orderByDesc('technology_changes.id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return response()->json($changes);
    }

    /**
     * GET /api/technology-changes/summary
     *
     * Menampilkan ringkasan perubahan teknologi.
     */
    public function summary(Request $request): JsonResponse
    {
        // Ringkasan menggunakan filter yang sama dengan daftar.
        $filters = $this->validateFilters($request);

        $query = $this->applyFilters(
            TechnologyChange::query(),
            $filters
        );

        $totalChanges = (clone $query)->count();

        $counts = (clone $query)
            ->select('technology_changes.change_type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('technology_changes.change_type')
            ->pluck('total', 'change_type');

        // Hitung jumlah website unik yang terdampak.
        $affectedWebsites = (clone $query)
            ->join(
                'scans as change_scans',
                'change_scans.id',
                '=',
                'technology_changes.scan_id'
            )
            ->distinct()
            ->count('change_scans.website_id');

        return response()->json([
            'data' => [
                'total_changes' => $totalChanges,

                'added' => (int) $counts->get('added', 0),

                'removed' => (int) $counts->get('removed', 0),

                'version_changed' => (int) $counts->get(
                    'version_changed',
                    0
                ),

                'affected_websites' => $affectedWebsites,
            ],
        ]);
    }

    /**
     * GET /api/technology-changes/{technologyChange}
     *
     * Menampilkan detail satu perubahan teknologi.
     */
    public function show(
        TechnologyChange $technologyChange
    ): JsonResponse {
        $technologyChange->load(
            $this->relationships()
        );

        return response()->json([
            'data' => $technologyChange,
        ]);
    }
}
