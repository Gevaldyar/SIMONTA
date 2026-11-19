<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteRequest;
use App\Http\Requests\UpdateWebsiteRequest;
use App\Models\Opd;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    /**
     * Menampilkan seluruh website.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->listWebsites(
            Website::query(),
            $request
        );
    }

    /**
     * Menampilkan website milik OPD tertentu.
     */
    public function indexForOpd(
        Request $request,
        Opd $opd
    ): JsonResponse {
        return $this->listWebsites(
            $opd->websites(),
            $request
        );
    }

    /**
     * Fungsi bersama untuk pagination dan filter website.
     */
    private function listWebsites(
        $query,
        Request $request
    ): JsonResponse {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'monitoring_enabled' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query->with('opd:id,name')
            ->withCount('scans');

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        if (array_key_exists('monitoring_enabled', $filters)) {
            $query->where(
                'monitoring_enabled',
                $filters['monitoring_enabled']
            );
        }

        $perPage = $filters['per_page'] ?? 15;

        return response()->json(
            $query->latest()->paginate($perPage)
        );
    }

    /**
     * Menambahkan website ke OPD.
     */
    public function storeForOpd(
        StoreWebsiteRequest $request,
        Opd $opd
    ): JsonResponse {
        if (!$opd->is_active) {
            return response()->json([
                'message' => 'Website tidak dapat ditambahkan ke OPD yang tidak aktif.',
            ], 409);
        }

        $website = $opd->websites()->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Website berhasil ditambahkan.',
            'data' => $website->load('opd:id,name'),
        ], 201);
    }

    /**
     * Menampilkan detail website.
     */
    public function show(Website $website): JsonResponse
    {
        $website->load('opd:id,name')
            ->loadCount('scans');

        return response()->json([
            'data' => $website,
        ]);
    }

    /**
     * Memperbarui data website.
     */
    
public function update(
    UpdateWebsiteRequest $request,
    Website $website
): JsonResponse {
    $previousInterval = (int) $website->monitoring_interval_minutes;
    $wasMonitoringEnabled = (bool) $website->monitoring_enabled;
    $wasActive = (bool) $website->is_active;

    $website->update($request->validated());

    $isMonitoringEligible = $website->is_active
        && $website->monitoring_enabled;

    if (! $isMonitoringEligible) {
        // Website tidak aktif atau monitoring dinonaktifkan.
        // Hapus jadwal berikutnya agar tidak ada jadwal kedaluwarsa
        // yang langsung dijalankan saat monitoring diaktifkan kembali.
        $website->forceFill([
            'next_scan_at' => null,
        ])->save();
    } elseif (
        $previousInterval !== (int) $website->monitoring_interval_minutes
        || ! $wasMonitoringEnabled
        || ! $wasActive
    ) {
        // Interval berubah atau monitoring baru saja diaktifkan.
        // Jadwalkan pemeriksaan berikutnya berdasarkan konfigurasi baru.
        $website->forceFill([
            'next_scan_at' => now()->addMinutes(
                (int) $website->monitoring_interval_minutes
            ),
        ])->save();
    }

    return response()->json([
        'message' => 'Website berhasil diperbarui.',
        'data' => $website->fresh()
            ->load('opd:id,name')
            ->loadCount('scans'),
    ]);
}


    /**
     * Menghapus website menggunakan soft delete.
     */
    public function destroy(Website $website): JsonResponse
    {
        $website->delete();

        return response()->json([
            'message' => 'Website berhasil dihapus.',
        ]);
    }
}
