<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Throwable;

class WebsiteSecurityAssessmentService
{
    /**
     * Menganalisis indikator keamanan dari fingerprint website.
     *
     * Tidak melakukan request jaringan tambahan.
     * HTML hanya dianalisis dalam memori dan tidak disimpan
     * sebagai bukti mentah.
     */
    public function assess(array $fingerprint): array
    {
        $html = $fingerprint['html'] ?? '';

        if (!is_string($html) || trim($html) === '') {
            return $this->unknownAssessment(
                'Respons tidak menyediakan HTML yang dapat dianalisis.'
            );
        }

        $dom = $this->parseHtml($html);

        if (!$dom) {
            return $this->unknownAssessment(
                'Dokumen HTML tidak dapat dianalisis.'
            );
        }

        $findings = [];
        $riskScore = 0;

        // Ambil script sebelum elemen DOM dimodifikasi.
        $scriptContents = [];

        foreach ($dom->getElementsByTagName('script') as $script) {
            $scriptContents[] = $script->textContent;
        }

        /*
         * 1. DETEKSI WEBSITE DEFACEMENT
         *
         * Memeriksa teks halaman, bukan kode JavaScript.
         */
        $visibleText = $this->extractVisibleText($dom);

        if (
            preg_match(
                '/\b(hacked\s+by|defaced\s+by|owned\s+by|pwned\s+by|website\s+defaced|site\s+hacked)\b/i',
                $visibleText,
                $matches
            )
        ) {
            $riskScore += 60;

            $findings[] = $this->finding(
                code: 'defacement_indicator',
                severity: 'high',
                title: 'Indikator Website Defacement',
                description: 'Halaman mengandung frasa yang sering ditemukan pada halaman website yang mengalami defacement.',
                evidence: [
                    'matched_marker' => substr($matches[1], 0, 100),
                ]
            );
        }

        /*
         * 2. DETEKSI JAVASCRIPT MENCURIGAKAN
         *
         * Satu pola saja tidak cukup untuk menghasilkan temuan.
         * Minimal dua pola harus ditemukan.
         */
        $combinedScripts = implode("\n", $scriptContents);

        $scriptPatterns = [
            'eval_atob' => '/\beval\s*\(\s*atob\s*\(/i',
            'eval_unescape' => '/\beval\s*\(\s*unescape\s*\(/i',
            'new_function' => '/\bnew\s+Function\s*\(/i',
            'document_write_encoded' => '/\bdocument\.write\s*\(\s*(?:atob|unescape)\s*\(/i',
            'encoded_base64_execution' => '/\batob\s*\(\s*[\'"][A-Za-z0-9+\/]{80,}={0,2}[\'"]\s*\)/i',
        ];

        $matchedPatterns = [];

        foreach ($scriptPatterns as $name => $pattern) {
            if (preg_match($pattern, $combinedScripts)) {
                $matchedPatterns[] = $name;
            }
        }

        if (count($matchedPatterns) >= 2) {
            $severity = count($matchedPatterns) >= 3
                ? 'high'
                : 'medium';

            $score = $severity === 'high' ? 50 : 35;

            $riskScore += $score;

            $findings[] = $this->finding(
                code: 'suspicious_javascript',
                severity: $severity,
                title: 'Pola JavaScript Mencurigakan',
                description: 'Ditemukan beberapa pola eksekusi JavaScript dan obfuscation yang perlu diperiksa.',
                evidence: [
                    'matched_patterns' => $matchedPatterns,
                ]
            );
        }

        /*
         * 3. DETEKSI HIDDEN EXTERNAL IFRAME
         *
         * Iframe eksternal tidak otomatis berbahaya.
         * Temuan dibuat hanya ketika iframe juga tersembunyi.
         */
        $baseUrl = $fingerprint['final_url']
            ?? $fingerprint['url']
            ?? '';

        $iframeEvidence = [];

        foreach ($dom->getElementsByTagName('iframe') as $iframe) {
            $src = trim($iframe->getAttribute('src'));

            if ($src === '' || !$this->isHiddenElement($iframe)) {
                continue;
            }

            $iframeHost = $this->resolveHost($src, $baseUrl);
            $baseHost = $this->resolveHost($baseUrl, $baseUrl);

            if (
                $iframeHost === null
                || $baseHost === null
                || $this->isSameSiteHost($iframeHost, $baseHost)
            ) {
                continue;
            }

            $iframeEvidence[] = $iframeHost;

            if (count($iframeEvidence) >= 3) {
                break;
            }
        }

        if (!empty($iframeEvidence)) {
            $riskScore += 25;

            $findings[] = $this->finding(
                code: 'hidden_external_iframe',
                severity: 'medium',
                title: 'Hidden External Iframe',
                description: 'Ditemukan iframe tersembunyi yang memuat sumber dari domain eksternal.',
                evidence: [
                    'external_hosts' => array_values(
                        array_unique($iframeEvidence)
                    ),
                ]
            );
        }

        /*
         * 4. DETEKSI HIDDEN SEO SPAM
         *
         * Memeriksa tautan tersembunyi yang mengandung
         * istilah spam tertentu.
         */
        $spamKeywords = [
            'judi online',
            'slot online',
            'togel',
            'casino',
            'poker',
            'betting',
            'viagra',
            'payday loan',
        ];

        $spamEvidence = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            if (!$this->isHiddenElement($anchor)) {
                continue;
            }

            $text = strtolower($anchor->textContent);
            $href = strtolower($anchor->getAttribute('href'));

            foreach ($spamKeywords as $keyword) {
                if (
                    str_contains($text, $keyword)
                    || str_contains($href, str_replace(' ', '-', $keyword))
                ) {
                    $spamEvidence[] = $keyword;
                    break;
                }
            }

            if (count($spamEvidence) >= 5) {
                break;
            }
        }

        if (!empty($spamEvidence)) {
            $riskScore += 30;

            $findings[] = $this->finding(
                code: 'hidden_seo_spam',
                severity: 'medium',
                title: 'Indikator Hidden SEO Spam',
                description: 'Ditemukan tautan tersembunyi yang mengandung istilah yang sering digunakan pada spam SEO.',
                evidence: [
                    'matched_keywords' => array_values(
                        array_unique($spamEvidence)
                    ),
                ]
            );
        }

        /*
         * 5. DETEKSI CROSS-DOMAIN REDIRECT
         *
         * Ini hanya indikator untuk ditinjau.
         * Redirect antar-domain juga dapat terjadi secara sah.
         */
        $originalHost = $this->resolveHost(
            $fingerprint['url'] ?? '',
            $baseUrl
        );

        $finalHost = $this->resolveHost(
            $fingerprint['final_url'] ?? '',
            $baseUrl
        );

        if (
            $originalHost !== null
            && $finalHost !== null
            && !$this->isSameSiteHost($originalHost, $finalHost)
        ) {
            $riskScore += 10;

            $findings[] = $this->finding(
                code: 'cross_domain_redirect',
                severity: 'low',
                title: 'Redirect ke Domain Berbeda',
                description: 'Website berakhir pada domain yang berbeda dari domain awal. Periksa apakah perpindahan tersebut memang direncanakan.',
                evidence: [
                    'original_host' => $originalHost,
                    'final_host' => $finalHost,
                ]
            );
        }

        $riskScore = min(100, $riskScore);

        $status = match (true) {
            $riskScore >= 50 => 'high',
            $riskScore >= 20 => 'review',
            default => 'low',
        };

        return [
            'version' => '1.0',
            'status' => $status,
            'risk_score' => $riskScore,
            'finding_count' => count($findings),
            'findings' => $findings,
            'assessed_at' => now()->toISOString(),
            'html_analyzed' => true,
        ];
    }

    private function parseHtml(string $html): ?DOMDocument
    {
        $previousSetting = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');

            $loaded = @$dom->loadHTML(
                '<?xml encoding="UTF-8">' . $html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            return $loaded ? $dom : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }
    }

    private function extractVisibleText(DOMDocument $dom): string
    {
        foreach (['script', 'style', 'noscript', 'template'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);

            while ($nodes->length > 0) {
                $node = $nodes->item(0);

                $node->parentNode?->removeChild($node);
            }
        }

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $dom->textContent ?? ''
        );

        return is_string($text) ? trim($text) : '';
    }

    private function isHiddenElement(DOMElement $element): bool
    {
        if ($element->hasAttribute('hidden')) {
            return true;
        }

        if (
            strtolower($element->getAttribute('aria-hidden')) === 'true'
        ) {
            return true;
        }

        $style = strtolower(
            preg_replace(
                '/\s+/',
                '',
                $element->getAttribute('style')
            ) ?? ''
        );

        $hiddenPatterns = [
            '/display:none(?:!important)?/',
            '/visibility:hidden(?:!important)?/',
            '/opacity:0(?:\.0+)?(?:!important)?/',
            '/width:0(?:px)?(?:!important)?/',
            '/height:0(?:px)?(?:!important)?/',
            '/left:-\d{3,}px/',
            '/clip-path:inset\(50%\)/',
        ];

        foreach ($hiddenPatterns as $pattern) {
            if (preg_match($pattern, $style)) {
                return true;
            }
        }

        return $element->getAttribute('width') === '0'
            || $element->getAttribute('height') === '0';
    }

    private function resolveHost(
        string $url,
        string $baseUrl
    ): ?string {
        if (trim($url) === '') {
            return null;
        }

        $resolved = $url;

        // Tangani URL relatif.
        if (
            !preg_match('/^https?:\/\//i', $url)
            && !str_starts_with($url, '//')
        ) {
            $baseHost = parse_url($baseUrl, PHP_URL_HOST);
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            if (!$baseHost) {
                return null;
            }

            if (str_starts_with($url, '/')) {
                $resolved = "{$scheme}://{$baseHost}{$url}";
            } else {
                $resolved = "{$scheme}://{$baseHost}/{$url}";
            }
        } elseif (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $resolved = "{$scheme}:{$url}";
        }

        $host = parse_url($resolved, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        return strtolower(rtrim($host, '.'));
    }

    private function isSameSiteHost(
        string $hostA,
        string $hostB
    ): bool {
        $hostA = preg_replace('/^www\./i', '', $hostA);
        $hostB = preg_replace('/^www\./i', '', $hostB);

        return $hostA === $hostB
            || str_ends_with($hostA, '.' . $hostB)
            || str_ends_with($hostB, '.' . $hostA);
    }

    private function finding(
        string $code,
        string $severity,
        string $title,
        string $description,
        array $evidence
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'evidence' => $evidence,
        ];
    }

    private function unknownAssessment(string $reason): array
    {
        return [
            'version' => '1.0',
            'status' => 'unknown',
            'risk_score' => null,
            'finding_count' => 0,
            'findings' => [],
            'reason' => $reason,
            'assessed_at' => now()->toISOString(),
            'html_analyzed' => false,
        ];
    }
}