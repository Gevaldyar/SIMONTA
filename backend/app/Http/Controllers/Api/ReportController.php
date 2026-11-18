<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'queued',
                    'running',
                    'completed',
                    'failed',
                ]),
            ],
            'opd_id' => [
                'nullable',
                'integer',
                'exists:opds,id',
            ],
            'website_id' => [
                'nullable',
                'integer',
                'exists:websites,id',
            ],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Query laporan
        |--------------------------------------------------------------------------
        */

        $query = Scan::query()
            ->with([
                'website' => function ($websiteQuery) {
                    $websiteQuery
                        ->withTrashed()
                        ->with('opd');
                },

                // Relasi yang benar pada model Scan.
                'scanTechnologies.technology',
            ]);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['website_id'])) {
            $query->where(
                'website_id',
                $validated['website_id']
            );
        }

        if (! empty($validated['opd_id'])) {
            $opdId = $validated['opd_id'];

            $query->whereHas('website', function ($websiteQuery) use ($opdId) {
                $websiteQuery
                    ->withTrashed()
                    ->where('opd_id', $opdId);
            });
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $validated['date_to']
            );
        }

        $filename = 'laporan-monitoring-simonta-'
            . now()->format('Ymd-His')
            . '.csv';

        /*
        |--------------------------------------------------------------------------
        | Export CSV
        |--------------------------------------------------------------------------
        */

        return response()->streamDownload(
            function () use ($query) {
                $output = fopen('php://output', 'w');

                if ($output === false) {
                    throw new \RuntimeException(
                        'Gagal membuat file laporan.'
                    );
                }

                // UTF-8 BOM agar karakter terbaca di Excel.
                fwrite($output, "\xEF\xBB\xBF");

                // Menentukan pemisah kolom Excel.
                fwrite($output, "sep=;\r\n");

                // Header laporan.
                fputcsv($output, [
                    'Waktu Pemeriksaan',
                    'Nama OPD',
                    'Nama Website',
                    'URL Website',
                    'Status Pemeriksaan',
                    'HTTP Status Code',
                    'Response Time (ms)',
                    'Judul Halaman',
                    'Final URL',
                    'Jumlah Teknologi',
                    'Teknologi Terdeteksi',
                    'Pesan Error',
                ], ';', '"', '\\', "\r\n");

                /*
                |--------------------------------------------------------------------------
                | Perlindungan formula injection
                |--------------------------------------------------------------------------
                */

                $safeCsvValue = function ($value) {
                    $value = (string) ($value ?? '');

                    if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $value)) {
                        return "'" . $value;
                    }

                    return $value;
                };

                /*
                |--------------------------------------------------------------------------
                | Proses data secara bertahap
                |--------------------------------------------------------------------------
                */

                $query->chunkById(
                    500,
                    function ($scans) use ($output, $safeCsvValue) {
                        foreach ($scans as $scan) {
                            $website = $scan->website;
                            $opd = $website?->opd;

                            /*
                            |------------------------------------------------------
                            | Mengambil teknologi dari scanTechnologies
                            |------------------------------------------------------
                            */

                            $detectedTechnologies = $scan->scanTechnologies
                                ->map(function ($scanTechnology) {
                                    $technology = $scanTechnology->technology;

                                    if (! $technology) {
                                        return null;
                                    }

                                    $name = $technology->name;

                                    if ($scanTechnology->version) {
                                        $name .= ' ' . $scanTechnology->version;
                                    }

                                    $name .= ' (Confidence: '
                                        . $scanTechnology->confidence
                                        . '%)';

                                    return $name;
                                })
                                ->filter()
                                ->implode(' | ');

                            /*
                            |------------------------------------------------------
                            | Waktu pemeriksaan
                            |------------------------------------------------------
                            */

                            $scanTime = $scan->completed_at
                                ?? $scan->created_at;

                            /*
                            |------------------------------------------------------
                            | Susun baris laporan
                            |------------------------------------------------------
                            */

                            $row = [
                                $scanTime?->format('Y-m-d H:i:s'),

                                $opd?->name ?? '-',

                                $website?->name ?? '-',

                                $website?->url ?? '-',

                                $scan->status,

                                $scan->http_status_code ?? '-',

                                $scan->response_time_ms ?? '-',

                                $scan->page_title ?? '-',

                                $scan->final_url ?? '-',

                                $scan->scanTechnologies->count(),

                                $detectedTechnologies ?: 'Tidak terdeteksi',

                                $scan->error_message ?? '-',
                            ];

                            fputcsv(
                                $output,
                                array_map($safeCsvValue, $row),
                                ';',
                                '"',
                                '\\',
                                "\r\n"
                            );
                        }
                    },
                    'id'
                );

                fclose($output);
            },

            $filename,

            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }
}