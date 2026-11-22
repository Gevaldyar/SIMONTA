<?php

namespace Tests\Unit;

use App\Services\SecurityHeadersAuditService;
use PHPUnit\Framework\TestCase;

class SecurityHeadersAuditServiceTest extends TestCase
{
    private function audit(
        array $headers,
        string $url = 'https://example.com'
    ): array {
        return (new SecurityHeadersAuditService())->assess(
            $headers,
            $url
        );
    }

    public function test_complete_security_headers_receive_full_score(): void
    {
        $result = $this->audit([
            'content-security-policy' => [
                "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
            ],
            'strict-transport-security' => [
                'max-age=63072000; includeSubDomains',
            ],
            'x-content-type-options' => ['nosniff'],
            'x-frame-options' => ['DENY'],
            'referrer-policy' => ['no-referrer'],
            'permissions-policy' => [
                'camera=(), microphone=(), geolocation=()',
            ],
        ]);

        $this->assertSame(100, $result['score']);
        $this->assertSame('strong', $result['status']);
        $this->assertSame(0, $result['finding_count']);
    }

    public function test_diskominfo_headers_detect_duplicate_conflicts(): void
    {
        $result = $this->audit([
            'content-security-policy' => [
                "frame-ancestors 'self'",
            ],
            'strict-transport-security' => [
                'max-age=31536000; includeSubDomains; preload',
                'max-age=15724800; preload',
            ],
            'x-content-type-options' => ['nosniff'],
            'x-frame-options' => [
                'DENY',
                'SAMEORIGIN',
            ],
            'referrer-policy' => [
                'strict-origin-when-cross-origin',
            ],
            'permissions-policy' => [
                'camera=(), microphone=(), geolocation=(), interest-cohort=()',
            ],
        ]);

        $this->assertSame(60, $result['score']);
        $this->assertSame('needs_review', $result['status']);
        $this->assertSame(3, $result['finding_count']);

        $this->assertSame(
            'conflict',
            $result['findings'][1]['status']
        );

        $this->assertSame(
            'conflict',
            $result['findings'][3]['status']
        );
    }

    public function test_hsts_is_not_applicable_to_http_url(): void
    {
        $result = $this->audit([
            'content-security-policy' => [
                "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
            ],
            'x-content-type-options' => ['nosniff'],
            'x-frame-options' => ['DENY'],
            'referrer-policy' => ['no-referrer'],
            'permissions-policy' => [
                'camera=(), microphone=(), geolocation=()',
            ],
        ], 'http://example.com');

        $this->assertSame(100, $result['score']);
        $this->assertSame(80, $result['applicable_max_score']);

        $this->assertSame(
            'not_applicable',
            $result['findings'][1]['status']
        );
    }

    public function test_unsafe_csp_is_flagged(): void
    {
        $result = $this->audit([
            'content-security-policy' => [
                "default-src *; script-src 'unsafe-inline' 'unsafe-eval'",
            ],
        ]);

        $this->assertSame(
            'warning',
            $result['findings'][0]['status']
        );

        $this->assertLessThanOrEqual(
            10,
            $result['findings'][0]['score']
        );
    }

    public function test_report_only_csp_is_not_counted_as_enforced_csp(): void
    {
        $result = $this->audit([
            'content-security-policy-report-only' => [
                "default-src 'self'",
            ],
        ]);

        $this->assertSame(
            'report_only',
            $result['findings'][0]['status']
        );
    }

    public function test_missing_headers_receive_zero_score(): void
    {
        $result = $this->audit([]);

        $this->assertSame(0, $result['score']);
        $this->assertSame('weak', $result['status']);
        $this->assertSame(6, $result['finding_count']);
    }
}