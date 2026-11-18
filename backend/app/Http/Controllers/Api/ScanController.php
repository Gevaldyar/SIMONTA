<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\Website;
use App\Services\WebsiteScanService;
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

        return response()->json(
            $query->paginate($filters['per_page'] ?? 15)
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
    public function store(
        Website $website,
        WebsiteScanService $scanService
    ): JsonResponse {
        if (!$website->is_active) {
            return response()->json([
                'message' => 'Website tidak aktif dan tidak dapat diperiksa.',
            ], 409);
        }

        $scan = $scanService->scan($website);

        $scan->load([
            'website',
            'scanTechnologies.technology',
            'scanTechnologies.signature',
        ]);

        $message = $scan->status === 'completed'
            ? 'Pemeriksaan website berhasil diselesaikan.'
            : 'Pemeriksaan selesai dengan status gagal.';

        return response()->json([
            'message' => $message,
            'data' => $scan,
        ], 201);
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
        ]);

        return response()->json([
            'data' => $scan,
        ]);
    }
}
