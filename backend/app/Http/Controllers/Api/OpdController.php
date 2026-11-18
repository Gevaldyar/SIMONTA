<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOpdRequest;
use App\Http\Requests\UpdateOpdRequest;
use App\Models\Opd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpdController extends Controller
{
    /**
     * Menampilkan daftar OPD.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Opd::query()
            ->withCount('websites');

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return response()->json(
            $query->orderBy('name')->paginate($perPage)
        );
    }

    /**
     * Menyimpan OPD baru.
     */
    public function store(StoreOpdRequest $request): JsonResponse
    {
        $opd = Opd::create($request->validated());

        return response()->json([
            'message' => 'OPD berhasil ditambahkan.',
            'data' => $opd,
        ], 201);
    }

    /**
     * Menampilkan detail OPD.
     */
    public function show(Opd $opd): JsonResponse
    {
        $opd->loadCount('websites');

        return response()->json([
            'data' => $opd,
        ]);
    }

    /**
     * Memperbarui OPD.
     */
    public function update(
        UpdateOpdRequest $request,
        Opd $opd
    ): JsonResponse {
        $opd->update($request->validated());

        return response()->json([
            'message' => 'Data OPD berhasil diperbarui.',
            'data' => $opd->fresh()->loadCount('websites'),
        ]);
    }

    /**
     * Menonaktifkan OPD secara soft delete.
     */
    public function destroy(Opd $opd): JsonResponse
    {
        // OPD tidak boleh dihapus selama masih memiliki
        // website yang belum dihapus.
        if ($opd->websites()->exists()) {
            return response()->json([
                'message' => 'OPD tidak dapat dihapus karena masih memiliki website aktif atau terdaftar.',
            ], 409);
        }

        $opd->delete();

        return response()->json([
            'message' => 'OPD berhasil dihapus.',
        ]);
    }
}
