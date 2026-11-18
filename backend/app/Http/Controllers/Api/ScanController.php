<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\Website;
use App\Jobs\RunWebsiteScanJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScanController extends Controller
{
    /**
     * Menampilkan seluruh riwayat pemeriksaan.
     */
    
public function index(Request $request): JsonResponse
{
    $filters = $request->validate([
        'status' => [
            'sometimes',
            Rule::in([
                'queued',
                'running',
                'completed',
                'failed',
            ]),
        ],

        'website_id' => [
            'sometimes',
            'integer',
            'exists:websites,id',
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

    $query = Scan::query()
        ->with([
            'website' => function ($query) {
                $query->withTrashed()
                    ->select('id', 'opd_id', 'name', 'url')
                    ->with([
                        'opd' => fn ($q) => $q->withTrashed()
                            ->select('id', 'name', 'code'),
                    ]);
            },
        ])
        ->withCount('scanTechnologies')
        ->latest('id');

    if (isset($filters['status'])) {
        $query->where('status', $filters['status']);
    }

    if (isset($filters['website_id'])) {
        $query->where('website_id', $filters['website_id']);
    }

    // Filter menggunakan tanggal dibuatnya record scan.
    if (isset($filters['date_from'])) {
        $query->whereDate(
            'scans.created_at',
            '>=',
            $filters['date_from']
        );
    }

    if (isset($filters['date_to'])) {
        $query->whereDate(
            'scans.created_at',
            '<=',
            $filters['date_to']
        );
    }

    return response()->json(
        $query->paginate($filters['per_page'] ?? 20)
    );
}


    /**
     * Menampilkan riwayat pemeriksaan satu website.
     */
    public function indexForWebsite(
        Request $request,
        Website $website
    ): JsonResponse {
        $filters = $request->validate([
            'status' => [
                'sometimes',
                Rule::in([
                    'queued',
                    'running',
                    'completed',
                    'failed',
                ]),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = $website->scans()
            ->withCount('scanTechnologies')
            ->latest('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json(
            $query->paginate($filters['per_page'] ?? 15)
        );
    }

    /**
     * Menjalankan pemeriksaan website secara manual.
     */
    
/**
 * Memasukkan pemeriksaan website ke antrean.
 */
    public function store(Website $website): JsonResponse
    {
        $result = DB::transaction(function () use ($website) {

            // Kunci data website agar tidak terjadi
            // pembuatan scan ganda secara bersamaan.
            $lockedWebsite = Website::query()
                ->with('opd')
                ->whereKey($website->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$lockedWebsite->is_active
                || !$lockedWebsite->opd?->is_active
            ) {
                return [
                    'error' => 'inactive',
                ];
            }

            // Periksa apakah website masih memiliki scan
            // yang sedang menunggu atau sedang berjalan.
            $hasPendingScan = $lockedWebsite->scans()
                ->whereIn('status', ['queued', 'running'])
                ->exists();

            if ($hasPendingScan) {
                return [
                    'error' => 'pending',
                ];
            }

            // Buat riwayat scan dengan status queued.
            $scan = $lockedWebsite->scans()->create([
                'status' => 'queued',
                'scheduled_for' => now(),
            ]);

            // Jika monitoring otomatis aktif, atur jadwal
            // pemeriksaan otomatis berikutnya.
            if ($lockedWebsite->monitoring_enabled) {
                $lockedWebsite->forceFill([
                    'next_scan_at' => now()->addMinutes(
                    $lockedWebsite->monitoring_interval_minutes
                ),
            ])->save();
        }

        return [
            'scan' => $scan,
        ];
    });

    if (($result['error'] ?? null) === 'inactive') {
        return response()->json([
            'message' => 'Website atau OPD tidak aktif dan tidak dapat diperiksa.',
        ], 409);
    }

    if (($result['error'] ?? null) === 'pending') {
        return response()->json([
            'message' => 'Website sudah memiliki pemeriksaan yang sedang menunggu atau berjalan.',
        ], 409);
    }

    $scan = $result['scan'];

    // Dispatch setelah data scan berhasil disimpan.
    RunWebsiteScanJob::dispatch($scan->id)->afterCommit();

    return response()->json([
        'message' => 'Pemeriksaan website berhasil dimasukkan ke antrean.',
        'data' => $scan,
    ], 202);
    }

    /**
     * Menampilkan detail satu pemeriksaan.
     */
    public function show(Scan $scan): JsonResponse
    {
        $scan->load([
    'website' => function ($query) {
        $query->withTrashed()
            ->with([
                'opd' => fn ($q) => $q->withTrashed(),
            ]);
    },

    'scanTechnologies.technology',
    'scanTechnologies.signature',

    'technologyChanges.technology',
    'technologyChanges.previousScan',
]);

        return response()->json([
            'data' => $scan,
        ]);
    }
}
