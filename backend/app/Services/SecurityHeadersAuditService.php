<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

class SecurityHeadersAuditService
{
    private const VERSION = '1.0.0';

    private const WEIGHTS = [
        'content-security-policy' => 30,
        'strict-transport-security' => 20,
        'x-content-type-options' => 15,
        'x-frame-options' => 15,
        'referrer-policy' => 10,
        'permissions-policy' => 10,
    ];

    /**
     * Menganalisis konfigurasi HTTP Security Headers.
     *
     * Penilaian ini merupakan pemeriksaan heuristik konfigurasi
     * response headers, bukan pengujian penetrasi atau sertifikasi.
     */
    public function assess(array $headers, string $url): array
    {
        $normalizedHeaders = [];

        foreach ($headers as $name => $values) {
            $normalizedHeaders[strtolower(trim((string) $name))] = $values;
        }

        $isHttps = strtolower(
            (string) parse_url($url, PHP_URL_SCHEME)
        ) === 'https';

        $findings = [];
        $earnedScore = 0;
        $applicableMaxScore = 100;

        $cspFinding = $this->assessContentSecurityPolicy(
            $normalizedHeaders
        );

        $findings[] = $cspFinding;
        $earnedScore += $cspFinding['score'];

        $cspValues = $this->values(
            $normalizedHeaders,
            'content-security-policy'
        );

        $cspHasFrameAncestors = $this->hasDirective(
            implode('; ', $cspValues),
            'frame-ancestors'
        );

        $hstsFinding = $this->assessHsts(
            $normalizedHeaders,
            $isHttps
        );

        $findings[] = $hstsFinding;
        $earnedScore += $hstsFinding['score'];

        if ($hstsFinding['status'] === 'not_applicable') {
            $applicableMaxScore -= self::WEIGHTS[
                'strict-transport-security'
            ];
        }

        $xctoFinding = $this->assessXContentTypeOptions(
            $normalizedHeaders
        );

        $findings[] = $xctoFinding;
        $earnedScore += $xctoFinding['score'];

        $xfoFinding = $this->assessXFrameOptions(
            $normalizedHeaders,
            $cspHasFrameAncestors
        );

        $findings[] = $xfoFinding;
        $earnedScore += $xfoFinding['score'];

        $referrerFinding = $this->assessReferrerPolicy(
            $normalizedHeaders
        );

        $findings[] = $referrerFinding;
        $earnedScore += $referrerFinding['score'];

        $permissionsFinding = $this->assessPermissionsPolicy(
            $normalizedHeaders
        );

        $findings[] = $permissionsFinding;
        $earnedScore += $permissionsFinding['score'];

        $score = $applicableMaxScore > 0
            ? (int) round(
                ($earnedScore / $applicableMaxScore) * 100
            )
            : null;

        $status = $this->aggregateStatus($score);

        $findingCount = count(array_filter(
            $findings,
            fn (array $finding) => !in_array(
                $finding['status'],
                ['good', 'not_applicable'],
                true
            )
        ));

        $presentHeaders = count(array_filter(
            $findings,
            fn (array $finding) => !in_array(
                $finding['status'],
                ['missing', 'not_applicable'],
                true
            )
        ));

        return [
            'version' => self::VERSION,
            'status' => $status,
            'score' => $score,
            'max_score' => 100,
            'applicable_max_score' => $applicableMaxScore,
            'finding_count' => $findingCount,
            'headers_present' => $presentHeaders,
            'headers_checked' => count(self::WEIGHTS),
            'checked_at' => (
                new DateTimeImmutable('now', new DateTimeZone('UTC'))
            )->format(DATE_ATOM),
            'findings' => $findings,
        ];
    }

    /**
     * Content Security Policy.
     */
    private function assessContentSecurityPolicy(array $headers): array
    {
        $values = $this->values(
            $headers,
            'content-security-policy'
        );

        $reportOnly = $this->values(
            $headers,
            'content-security-policy-report-only'
        );

        if (empty($values)) {
            if (!empty($reportOnly)) {
                return $this->finding(
                    key: 'content-security-policy',
                    label: 'Content Security Policy',
                    status: 'report_only',
                    score: 5,
                    maxScore: 30,
                    observed: $reportOnly,
                    summary: 'CSP hanya tersedia dalam mode Report-Only dan belum diterapkan sebagai kebijakan aktif.',
                    recommendation: 'Evaluasi laporan pelanggaran CSP, lalu terapkan kebijakan melalui Content-Security-Policy setelah pengujian.',
                );
            }

            return $this->finding(
                key: 'content-security-policy',
                label: 'Content Security Policy',
                status: 'missing',
                score: 0,
                maxScore: 30,
                observed: null,
                summary: 'Content-Security-Policy tidak ditemukan.',
                recommendation: 'Susun CSP sesuai sumber resource yang benar-benar digunakan website. Mulai dengan default-src, object-src, dan base-uri setelah menguji kompatibilitas.',
            );
        }

        // Beberapa CSP enforcement header diperlakukan sebagai
        // beberapa kebijakan yang sama-sama berlaku, bukan konflik.
        $policy = strtolower(implode('; ', $values));

        $hasDefaultSrc = $this->hasDirective($policy, 'default-src');
        $hasScriptSrc = $this->hasDirective($policy, 'script-src');
        $hasObjectNone = (bool) preg_match(
            '/(?:^|;)\s*object-src\s+[^;]*[\'"]none[\'"]/i',
            $policy
        );
        $hasBaseUri = $this->hasDirective($policy, 'base-uri');
        $hasFrameAncestors = $this->hasDirective(
            $policy,
            'frame-ancestors'
        );

        $hasUnsafeInline = (bool) preg_match(
            '/[\'"]unsafe-inline[\'"]/i',
            $policy
        );

        $hasUnsafeEval = (bool) preg_match(
            '/[\'"]unsafe-eval[\'"]/i',
            $policy
        );

        $hasWildcard = (bool) preg_match(
            '/(?:^|[\s;])\*(?:[\s;]|$)/',
            $policy
        );

        if ($hasDefaultSrc && $hasObjectNone && $hasBaseUri) {
            $score = 30;
        } elseif ($hasDefaultSrc || $hasScriptSrc) {
            $score = 22;
        } elseif ($hasFrameAncestors) {
            $score = 15;
        } else {
            $score = 10;
        }

        if ($hasUnsafeInline || $hasUnsafeEval || $hasWildcard) {
            $score = min($score, 10);

            $issues = [];

            if ($hasUnsafeInline) {
                $issues[] = 'unsafe-inline';
            }

            if ($hasUnsafeEval) {
                $issues[] = 'unsafe-eval';
            }

            if ($hasWildcard) {
                $issues[] = 'wildcard source';
            }

            return $this->finding(
                key: 'content-security-policy',
                label: 'Content Security Policy',
                status: 'warning',
                score: $score,
                maxScore: 30,
                observed: $values,
                summary: 'CSP memuat sumber atau direktif yang terlalu permisif: '
                    . implode(', ', $issues) . '.',
                recommendation: 'Tinjau penggunaan unsafe-inline, unsafe-eval, dan wildcard. Pertimbangkan nonce atau hash serta daftar sumber yang lebih spesifik.',
            );
        }

        if ($score === 30) {
            return $this->finding(
                key: 'content-security-policy',
                label: 'Content Security Policy',
                status: 'good',
                score: 30,
                maxScore: 30,
                observed: $values,
                summary: 'CSP memiliki default-src, object-src none, dan base-uri.',
                recommendation: 'Pertahankan kebijakan dan evaluasi perubahan resource secara berkala.',
            );
        }

        return $this->finding(
            key: 'content-security-policy',
            label: 'Content Security Policy',
            status: 'partial',
            score: $score,
            maxScore: 30,
            observed: $values,
            summary: 'CSP tersedia, tetapi belum mencakup sejumlah direktif dasar yang diperiksa SIMONTA.',
            recommendation: 'Evaluasi penambahan default-src, object-src none, dan base-uri sesuai kebutuhan aplikasi. Kebijakan harus diuji agar resource website tidak rusak.',
        );
    }

    /**
     * Strict Transport Security.
     */
    private function assessHsts(
        array $headers,
        bool $isHttps
    ): array {
        $values = $this->values(
            $headers,
            'strict-transport-security'
        );

        if (!$isHttps) {
            return $this->finding(
                key: 'strict-transport-security',
                label: 'HTTP Strict Transport Security',
                status: 'not_applicable',
                score: 0,
                maxScore: 0,
                observed: $values,
                summary: 'HSTS tidak dinilai karena URL akhir pemeriksaan menggunakan HTTP.',
                recommendation: 'Pastikan website menggunakan HTTPS dan mengirim HSTS melalui respons HTTPS.',
            );
        }

        if (empty($values)) {
            return $this->finding(
                key: 'strict-transport-security',
                label: 'HTTP Strict Transport Security',
                status: 'missing',
                score: 0,
                maxScore: 20,
                observed: null,
                summary: 'Strict-Transport-Security tidak ditemukan pada respons HTTPS.',
                recommendation: 'Evaluasi penerapan HSTS dengan max-age yang memadai. Uji kesiapan seluruh subdomain sebelum menambahkan includeSubDomains.',
            );
        }

        if ($this->hasConflictingValues($values)) {
            return $this->finding(
                key: 'strict-transport-security',
                label: 'HTTP Strict Transport Security',
                status: 'conflict',
                score: 0,
                maxScore: 20,
                observed: $values,
                summary: 'Ditemukan beberapa nilai HSTS yang berbeda pada satu respons.',
                recommendation: 'Periksa konfigurasi reverse proxy, CDN, dan web server. Pastikan hanya satu konfigurasi HSTS yang konsisten dikirim.',
            );
        }

        $value = strtolower(implode('; ', $values));

        if (!preg_match('/max-age\s*=\s*(\d+)/i', $value, $matches)) {
            return $this->finding(
                key: 'strict-transport-security',
                label: 'HTTP Strict Transport Security',
                status: 'invalid',
                score: 0,
                maxScore: 20,
                observed: $values,
                summary: 'Direktif max-age tidak ditemukan atau tidak valid.',
                recommendation: 'Periksa sintaks HSTS dan tetapkan max-age dalam satuan detik.',
            );
        }

        $maxAge = (int) $matches[1];

        $hasSubdomains = (bool) preg_match(
            '/(?:^|;)\s*includesubdomains\s*(?:;|$)/i',
            $value
        );

        if ($maxAge >= 63072000 && $hasSubdomains) {
            $score = 20;
            $status = 'good';
            $summary = 'HSTS memiliki masa berlaku dua tahun atau lebih dan mencakup subdomain.';
        } elseif ($maxAge >= 31536000 && $hasSubdomains) {
            $score = 18;
            $status = 'good';
            $summary = 'HSTS memiliki masa berlaku minimal satu tahun dan mencakup subdomain.';
        } elseif ($maxAge >= 31536000) {
            $score = 14;
            $status = 'partial';
            $summary = 'Masa berlaku HSTS memadai, tetapi includeSubDomains belum digunakan.';
        } elseif ($maxAge >= 15552000) {
            $score = 9;
            $status = 'partial';
            $summary = 'HSTS tersedia, tetapi masa berlaku masih di bawah satu tahun.';
        } else {
            $score = 4;
            $status = 'warning';
            $summary = 'HSTS tersedia, tetapi nilai max-age relatif pendek.';
        }

        $recommendation = $hasSubdomains
            ? 'Pertahankan konfigurasi dan evaluasi masa berlaku secara berkala.'
            : 'Evaluasi includeSubDomains hanya setelah seluruh subdomain dipastikan mendukung HTTPS.';

        return $this->finding(
            key: 'strict-transport-security',
            label: 'HTTP Strict Transport Security',
            status: $status,
            score: $score,
            maxScore: 20,
            observed: $values,
            summary: $summary,
            recommendation: $recommendation,
        );
    }

    /**
     * X-Content-Type-Options.
     */
    private function assessXContentTypeOptions(array $headers): array
    {
        $values = $this->values(
            $headers,
            'x-content-type-options'
        );

        if (empty($values)) {
            return $this->finding(
                key: 'x-content-type-options',
                label: 'X-Content-Type-Options',
                status: 'missing',
                score: 0,
                maxScore: 15,
                observed: null,
                summary: 'X-Content-Type-Options tidak ditemukan.',
                recommendation: 'Tambahkan X-Content-Type-Options: nosniff dan pastikan Content-Type setiap resource dikonfigurasi dengan benar.',
            );
        }

        if ($this->hasConflictingValues($values)) {
            return $this->finding(
                key: 'x-content-type-options',
                label: 'X-Content-Type-Options',
                status: 'conflict',
                score: 0,
                maxScore: 15,
                observed: $values,
                summary: 'Ditemukan nilai X-Content-Type-Options yang berbeda.',
                recommendation: 'Pastikan hanya satu nilai nosniff yang dikirim secara konsisten.',
            );
        }

        $value = strtolower(trim($values[0]));

        if ($value === 'nosniff') {
            return $this->finding(
                key: 'x-content-type-options',
                label: 'X-Content-Type-Options',
                status: 'good',
                score: 15,
                maxScore: 15,
                observed: $values,
                summary: 'Nilai nosniff ditemukan.',
                recommendation: 'Pertahankan konfigurasi nosniff.',
            );
        }

        return $this->finding(
            key: 'x-content-type-options',
            label: 'X-Content-Type-Options',
            status: 'invalid',
            score: 0,
            maxScore: 15,
            observed: $values,
            summary: 'Nilai header bukan nosniff.',
            recommendation: 'Gunakan nilai nosniff.',
        );
    }

    /**
     * X-Frame-Options dan CSP frame-ancestors.
     */
    private function assessXFrameOptions(
        array $headers,
        bool $cspHasFrameAncestors
    ): array {
        $values = $this->values(
            $headers,
            'x-frame-options'
        );

        if (empty($values)) {
            if ($cspHasFrameAncestors) {
                return $this->finding(
                    key: 'x-frame-options',
                    label: 'Frame Protection',
                    status: 'good',
                    score: 15,
                    maxScore: 15,
                    observed: null,
                    summary: 'X-Frame-Options tidak ditemukan, tetapi CSP frame-ancestors tersedia.',
                    recommendation: 'Pertahankan CSP frame-ancestors. Evaluasi X-Frame-Options sebagai fallback untuk browser lama bila diperlukan.',
                );
            }

            return $this->finding(
                key: 'x-frame-options',
                label: 'Frame Protection',
                status: 'missing',
                score: 0,
                maxScore: 15,
                observed: null,
                summary: 'X-Frame-Options dan CSP frame-ancestors tidak ditemukan.',
                recommendation: 'Terapkan X-Frame-Options DENY atau SAMEORIGIN, atau gunakan CSP frame-ancestors sesuai kebutuhan embedding.',
            );
        }

        if ($this->hasConflictingValues($values)) {
            return $this->finding(
                key: 'x-frame-options',
                label: 'Frame Protection',
                status: 'conflict',
                score: $cspHasFrameAncestors ? 10 : 0,
                maxScore: 15,
                observed: $values,
                summary: $cspHasFrameAncestors
                    ? 'X-Frame-Options memiliki nilai berbeda. CSP frame-ancestors tersedia sebagai kebijakan framing utama pada browser yang mendukungnya.'
                    : 'Ditemukan nilai X-Frame-Options yang saling bertentangan.',
                recommendation: 'Periksa konfigurasi web server, reverse proxy, atau CDN. Kirim satu nilai X-Frame-Options yang konsisten.',
            );
        }

        $value = strtolower(trim($values[0]));

        if (in_array($value, ['deny', 'sameorigin'], true)) {
            return $this->finding(
                key: 'x-frame-options',
                label: 'Frame Protection',
                status: 'good',
                score: 15,
                maxScore: 15,
                observed: $values,
                summary: "X-Frame-Options menggunakan nilai {$value}.",
                recommendation: 'Pertahankan kebijakan framing yang sesuai kebutuhan aplikasi.',
            );
        }

        return $this->finding(
            key: 'x-frame-options',
            label: 'Frame Protection',
            status: 'invalid',
            score: 0,
            maxScore: 15,
            observed: $values,
            summary: 'Nilai X-Frame-Options tidak dikenali atau tidak didukung.',
            recommendation: 'Gunakan DENY atau SAMEORIGIN. Untuk kebutuhan lebih fleksibel, gunakan CSP frame-ancestors.',
        );
    }

    /**
     * Referrer-Policy.
     */
    private function assessReferrerPolicy(array $headers): array
    {
        $values = $this->values(
            $headers,
            'referrer-policy'
        );

        if (empty($values)) {
            return $this->finding(
                key: 'referrer-policy',
                label: 'Referrer Policy',
                status: 'missing',
                score: 0,
                maxScore: 10,
                observed: null,
                summary: 'Referrer-Policy tidak ditemukan.',
                recommendation: 'Evaluasi strict-origin-when-cross-origin atau no-referrer sesuai kebutuhan privasi aplikasi.',
            );
        }

        $value = strtolower(trim(end($values)));

        $strongPolicies = [
            'no-referrer',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
        ];

        if (in_array($value, $strongPolicies, true)) {
            return $this->finding(
                key: 'referrer-policy',
                label: 'Referrer Policy',
                status: 'good',
                score: 10,
                maxScore: 10,
                observed: $values,
                summary: "Referrer-Policy menggunakan {$value}.",
                recommendation: 'Pertahankan kebijakan dan pastikan sesuai kebutuhan alur navigasi aplikasi.',
            );
        }

        $knownPolicies = [
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'unsafe-url',
        ];

        if (in_array($value, $knownPolicies, true)) {
            $score = $value === 'unsafe-url' ? 2 : 7;

            return $this->finding(
                key: 'referrer-policy',
                label: 'Referrer Policy',
                status: 'warning',
                score: $score,
                maxScore: 10,
                observed: $values,
                summary: "Referrer-Policy menggunakan {$value}, yang memberikan perlindungan informasi referrer lebih terbatas.",
                recommendation: 'Pertimbangkan strict-origin-when-cross-origin, same-origin, atau no-referrer sesuai kebutuhan aplikasi.',
            );
        }

        return $this->finding(
            key: 'referrer-policy',
            label: 'Referrer Policy',
            status: 'invalid',
            score: 0,
            maxScore: 10,
            observed: $values,
            summary: 'Nilai Referrer-Policy tidak dikenali.',
            recommendation: 'Gunakan nilai kebijakan yang didukung browser.',
        );
    }

    /**
     * Permissions-Policy.
     */
    private function assessPermissionsPolicy(array $headers): array
    {
        $values = $this->values(
            $headers,
            'permissions-policy'
        );

        if (empty($values)) {
            return $this->finding(
                key: 'permissions-policy',
                label: 'Permissions Policy',
                status: 'missing',
                score: 0,
                maxScore: 10,
                observed: null,
                summary: 'Permissions-Policy tidak ditemukan.',
                recommendation: 'Evaluasi pembatasan fitur browser yang tidak diperlukan, seperti kamera, mikrofon, dan geolokasi.',
            );
        }

        if ($this->hasConflictingValues($values)) {
            return $this->finding(
                key: 'permissions-policy',
                label: 'Permissions Policy',
                status: 'conflict',
                score: 4,
                maxScore: 10,
                observed: $values,
                summary: 'Ditemukan beberapa nilai Permissions-Policy yang berbeda.',
                recommendation: 'Periksa konfigurasi server dan pastikan kebijakan dikirim secara konsisten.',
            );
        }

        $value = strtolower(implode(', ', $values));

        $allowsAll = (bool) preg_match(
            '/(?:=\s*\(\s*\*\s*\)|=\s*\*)/',
            $value
        );

        if ($allowsAll) {
            return $this->finding(
                key: 'permissions-policy',
                label: 'Permissions Policy',
                status: 'warning',
                score: 2,
                maxScore: 10,
                observed: $values,
                summary: 'Ditemukan kebijakan yang mengizinkan fitur secara luas menggunakan wildcard.',
                recommendation: 'Batasi izin hanya untuk fitur dan origin yang benar-benar diperlukan.',
            );
        }

        $hasSensitiveRestrictions = (bool) preg_match(
            '/(?:camera|microphone|geolocation)\s*=\s*\(\s*\)/i',
            $value
        );

        if ($hasSensitiveRestrictions) {
            return $this->finding(
                key: 'permissions-policy',
                label: 'Permissions Policy',
                status: 'good',
                score: 10,
                maxScore: 10,
                observed: $values,
                summary: 'Kebijakan ditemukan dan setidaknya satu fitur sensitif dibatasi secara eksplisit.',
                recommendation: 'Tinjau daftar fitur yang diizinkan agar tetap sesuai kebutuhan layanan.',
            );
        }

        return $this->finding(
            key: 'permissions-policy',
            label: 'Permissions Policy',
            status: 'partial',
            score: 6,
            maxScore: 10,
            observed: $values,
            summary: 'Permissions-Policy tersedia, tetapi pembatasan fitur sensitif belum teridentifikasi oleh pemeriksaan SIMONTA.',
            recommendation: 'Evaluasi pembatasan kamera, mikrofon, dan geolokasi apabila fitur tersebut tidak digunakan.',
        );
    }

    private function finding(
        string $key,
        string $label,
        string $status,
        int $score,
        int $maxScore,
        ?array $observed,
        string $summary,
        string $recommendation
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'score' => $score,
            'max_score' => $maxScore,
            'observed' => $observed,
            'summary' => $summary,
            'recommendation' => $recommendation,
        ];
    }

    private function values(array $headers, string $name): array
    {
        $values = $headers[$name] ?? [];

        if (is_string($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn ($value) => trim((string) $value),
                $values
            ),
            fn ($value) => $value !== ''
        ));
    }

    private function hasConflictingValues(array $values): bool
    {
        $normalized = array_map(
            fn ($value) => strtolower(trim($value)),
            $values
        );

        return count(array_unique($normalized)) > 1;
    }

    private function hasDirective(string $policy, string $directive): bool
    {
        return (bool) preg_match(
            '/(?:^|;)\s*' . preg_quote($directive, '/') . '\s*(?:[^;]*)/i',
            $policy
        );
    }

    private function aggregateStatus(?int $score): string
    {
        if ($score === null) {
            return 'unknown';
        }

        return match (true) {
            $score >= 85 => 'strong',
            $score >= 70 => 'moderate',
            $score >= 50 => 'needs_review',
            default => 'weak',
        };
    }
}