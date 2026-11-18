<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\TechnologySignature;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class TechnologyDetectionService
{
    /**
     * Mendeteksi teknologi berdasarkan fingerprint website.
     *
     * Hasil pemeriksaan sebelumnya pada scan yang sama akan
     * diganti secara keseluruhan dengan hasil terbaru.
     */
    public function detect(
        Scan $scan,
        array $fingerprint
    ): EloquentCollection {
        $matches = [];

        // Ambil semua signature aktif beserta teknologinya.
        $signatures = TechnologySignature::query()
            ->with('technology')
            ->where('is_active', true)
            ->whereHas('technology', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        foreach ($signatures as $signature) {
            $technology = $signature->technology;

            if (!$technology) {
                continue;
            }

            $candidates = $this->getCandidates(
                $fingerprint,
                $signature
            );

            foreach ($candidates as $candidate) {
                if (!$this->matches(
                    $candidate,
                    $signature->pattern,
                    $signature->match_type
                )) {
                    continue;
                }

                $technologyId = $technology->id;

                $version = $this->extractVersion(
                    $candidate,
                    $signature->version_pattern
                );

                $confidence = min(
                    100,
                    max(0, (int) $signature->confidence)
                );

                if (!isset($matches[$technologyId])) {
                    $matches[$technologyId] = [
                        'signature_id' => $signature->id,
                        'version' => $version,
                        'confidence' => $confidence,
                        'evidence' => [],
                    ];
                }

                // Simpan informasi signature sebagai bukti.
                // Nilai mentah HTML, cookie, dan header tidak disimpan.
                $matches[$technologyId]['evidence'][] = [
                    'signature_id' => $signature->id,
                    'signature_name' => $signature->name,
                    'type' => $signature->type,
                    'target' => $signature->target,
                    'match_type' => $signature->match_type,
                    'pattern' => $signature->pattern,
                ];

                // Gunakan signature dengan confidence tertinggi
                // sebagai acuan versi dan sumber utama.
                if (
                    $confidence >
                    $matches[$technologyId]['confidence']
                ) {
                    $matches[$technologyId]['signature_id'] =
                        $signature->id;

                    $matches[$technologyId]['confidence'] =
                        $confidence;

                    if ($version !== null) {
                        $matches[$technologyId]['version'] = $version;
                    }
                } elseif (
                    $matches[$technologyId]['version'] === null
                    && $version !== null
                ) {
                    $matches[$technologyId]['version'] = $version;
                }

                // Satu signature cukup dicatat satu kali
                // meskipun cocok dengan beberapa kandidat.
                break;
            }
        }

        // Ganti hasil sebelumnya secara atomik.
        DB::transaction(function () use ($scan, $matches) {
            $scan->scanTechnologies()->delete();

            foreach ($matches as $technologyId => $result) {
                $scan->scanTechnologies()->create([
                    'technology_id' => $technologyId,
                    'signature_id' => $result['signature_id'],
                    'version' => $result['version'],
                    'confidence' => $result['confidence'],
                    'evidence' => $result['evidence'],
                ]);
            }
        }, 3);

        return $scan->scanTechnologies()
            ->with('technology')
            ->get();
    }

    /**
     * Mengambil kandidat berdasarkan tipe signature.
     *
     * Format fingerprint yang diterima:
     * headers, html, scripts, meta, cookies, url, final_url.
     */
    private function getCandidates(
        array $fingerprint,
        TechnologySignature $signature
    ): array {
        $type = strtolower($signature->type);
        $target = strtolower(trim((string) $signature->target));

        switch ($type) {
            case 'header':
                $values = [];

                foreach (($fingerprint['headers'] ?? []) as $name => $value) {
                    if (
                        $target !== ''
                        && strtolower((string) $name) !== $target
                    ) {
                        continue;
                    }

                    $values = array_merge(
                        $values,
                        $this->stringValues($value)
                    );
                }

                return array_values(array_unique($values));

            case 'html':
                return $this->stringValues(
                    $fingerprint['html'] ?? null
                );

            case 'script':
                return $this->stringValues(
                    $fingerprint['scripts'] ?? []
                );

            case 'meta':
                $values = [];

                foreach (($fingerprint['meta'] ?? []) as $key => $item) {
                    if (is_array($item)) {
                        $name = strtolower(
                            (string) (
                                $item['name']
                                ?? $item['property']
                                ?? ''
                            )
                        );

                        $content = $item['content'] ?? null;

                        if (
                            $target !== ''
                            && $name !== $target
                        ) {
                            continue;
                        }

                        $values = array_merge(
                            $values,
                            $this->stringValues($content)
                        );

                        continue;
                    }

                    $name = strtolower((string) $key);

                    if (
                        $target !== ''
                        && $name !== $target
                    ) {
                        continue;
                    }

                    $values = array_merge(
                        $values,
                        $this->stringValues($item)
                    );
                }

                return array_values(array_unique($values));

            case 'cookie':
                $values = [];

                foreach (($fingerprint['cookies'] ?? []) as $name => $value) {
                    if (is_int($name)) {
                        $values = array_merge(
                            $values,
                            $this->stringValues($value)
                        );

                        continue;
                    }

                    if (is_scalar($value)) {
                        $values[] = $name . '=' . $value;
                    }
                }

                return array_values(array_unique($values));

            case 'url':
                return $this->stringValues([
                    $fingerprint['url'] ?? null,
                    $fingerprint['final_url'] ?? null,
                ]);

            default:
                return [];
        }
    }

    /**
     * Mengubah nilai string atau array menjadi daftar string.
     */
    private function stringValues(mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            $string = (string) $value;

            return $string === '' ? [] : [$string];
        }

        if (!is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $item) {
            $values = array_merge(
                $values,
                $this->stringValues($item)
            );
        }

        return array_values(array_unique($values));
    }

    /**
     * Memeriksa kecocokan kandidat dengan pola signature.
     */
    private function matches(
        string $candidate,
        string $pattern,
        string $matchType
    ): bool {
        if (trim($pattern) === '') {
            return false;
        }

        switch (strtolower($matchType)) {
            case 'contains':
                return stripos($candidate, $pattern) !== false;

            case 'equals':
                return strcasecmp(
                    trim($candidate),
                    trim($pattern)
                ) === 0;

            case 'starts_with':
                return stripos($candidate, $pattern) === 0;

            case 'regex':
                if (strlen($pattern) > 512) {
                    return false;
                }

                return @preg_match($pattern, $candidate) === 1;

            default:
                return false;
        }
    }

    /**
     * Mengambil versi teknologi dari kandidat.
     *
     * version_pattern harus berupa regular expression
     * dengan grup penangkap untuk nomor versi.
     */
    private function extractVersion(
        string $candidate,
        ?string $versionPattern
    ): ?string {
        if (
            !$versionPattern
            || strlen($versionPattern) > 512
        ) {
            return null;
        }

        $matched = @preg_match(
            $versionPattern,
            $candidate,
            $matches
        );

        if ($matched !== 1) {
            return null;
        }

        $version = $matches[1] ?? $matches[0] ?? null;

        if (!is_string($version) || $version === '') {
            return null;
        }

        // Membatasi panjang versi agar tidak menyimpan
        // string yang tidak wajar.
        return substr($version, 0, 100);
    }
}
