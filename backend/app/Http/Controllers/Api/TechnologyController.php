<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Technology;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnologyController extends Controller
{
    /**
     * Mengambil jumlah website yang menggunakan setiap teknologi
     * berdasarkan scan completed terbaru per website.
     *
     * Scan failed yang lebih baru tidak menghapus hasil
     * identifikasi terakhir yang berhasil.
     */
    private function latestCompletedUsage(): QueryBuilder
    {
        return DB::table('scan_technologies as st')
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
    }

    /**
     * Menampilkan katalog teknologi.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $usage = $this->latestCompletedUsage();

        $query = Technology::query()
            ->leftJoinSub($usage, 'usage', function ($join) {
                $join->on(
                    'usage.technology_id',
                    '=',
                    'technologies.id'
                );
            })
            ->select('technologies.*')
            ->selectRaw(
                'COALESCE(usage.websites_count, 0) AS websites_count'
            );

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('technologies.name', 'like', "%{$search}%")
                    ->orWhere('technologies.slug', 'like', "%{$search}%")
                    ->orWhere('technologies.vendor', 'like', "%{$search}%");
            });
        }

        if (isset($filters['category'])) {
            $query->where(
                'technologies.category',
                $filters['category']
            );
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where(
                'technologies.is_active',
                $filters['is_active']
            );
        }

        return response()->json(
            $query->orderBy('technologies.category')
                ->orderBy('technologies.name')
                ->paginate($filters['per_page'] ?? 15)
        );
    }

    /**
     * Menampilkan detail teknologi dan signature.
     */
    public function show(Technology $technology): JsonResponse
    {
        $usage = $this->latestCompletedUsage();

        $detail = Technology::query()
            ->leftJoinSub($usage, 'usage', function ($join) {
                $join->on(
                    'usage.technology_id',
                    '=',
                    'technologies.id'
                );
            })
            ->select('technologies.*')
            ->selectRaw(
                'COALESCE(usage.websites_count, 0) AS websites_count'
            )
            ->where('technologies.id', $technology->id)
            ->firstOrFail();

        $detail->load('signatures');

        return response()->json([
            'data' => $detail,
        ]);
    }
}
