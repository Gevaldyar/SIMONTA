<?php

namespace Tests\Feature;

use App\Services\WebsiteSecurityAssessmentService;
use Tests\TestCase;

class WebsiteSecurityAssessmentServiceTest extends TestCase
{
    private function assess(
        string $html,
        string $url = 'https://opd.example.com',
        ?string $finalUrl = null
    ): array {
        return app(WebsiteSecurityAssessmentService::class)->assess([
            'url' => $url,
            'final_url' => $finalUrl ?? $url,
            'html' => $html,
        ]);
    }

    public function test_clean_html_returns_low_risk(): void
    {
        $result = $this->assess(
            '<html><head><title>Portal OPD</title></head>'
            . '<body><h1>Selamat Datang</h1></body></html>'
        );

        $this->assertSame('low', $result['status']);
        $this->assertSame(0, $result['risk_score']);
        $this->assertCount(0, $result['findings']);
        $this->assertTrue($result['html_analyzed']);
    }

    public function test_defacement_indicator_returns_high_risk(): void
    {
        $result = $this->assess(
            '<html><body><h1>Hacked by Security Team</h1></body></html>'
        );

        $this->assertSame('high', $result['status']);
        $this->assertGreaterThanOrEqual(50, $result['risk_score']);

        $this->assertSame(
            'defacement_indicator',
            $result['findings'][0]['code']
        );
    }

    public function test_multiple_suspicious_javascript_patterns_are_detected(): void
    {
        $result = $this->assess(
            '<html><body><script>'
            . 'eval(atob("encoded"));'
            . 'new Function("return true");'
            . '</script></body></html>'
        );

        $this->assertSame('review', $result['status']);

        $this->assertContains(
            'suspicious_javascript',
            array_column($result['findings'], 'code')
        );
    }

    public function test_hidden_external_iframe_is_detected(): void
    {
        $result = $this->assess(
            '<html><body>'
            . '<iframe src="https://external.example/frame" '
            . 'style="display:none;width:0;height:0"></iframe>'
            . '</body></html>'
        );

        $this->assertSame('review', $result['status']);

        $this->assertContains(
            'hidden_external_iframe',
            array_column($result['findings'], 'code')
        );
    }

    public function test_hidden_seo_spam_link_is_detected(): void
    {
        $result = $this->assess(
            '<html><body>'
            . '<a href="https://spam.example/judi-online" '
            . 'style="display:none">Judi Online</a>'
            . '</body></html>'
        );

        $this->assertSame('review', $result['status']);

        $this->assertContains(
            'hidden_seo_spam',
            array_column($result['findings'], 'code')
        );
    }

    public function test_cross_domain_redirect_is_recorded_as_low_risk_indicator(): void
    {
        $result = $this->assess(
            '<html><body><h1>Portal</h1></body></html>',
            'https://opd.example.com',
            'https://other.example.net'
        );

        $this->assertSame('low', $result['status']);
        $this->assertSame(10, $result['risk_score']);

        $this->assertContains(
            'cross_domain_redirect',
            array_column($result['findings'], 'code')
        );
    }

    public function test_same_site_subdomain_redirect_is_not_flagged(): void
    {
        $result = $this->assess(
            '<html><body><h1>Portal</h1></body></html>',
            'https://example.com',
            'https://www.example.com'
        );

        $this->assertSame('low', $result['status']);
        $this->assertSame(0, $result['risk_score']);
    }

    public function test_missing_html_returns_unknown_assessment(): void
    {
        $result = $this->assess('');

        $this->assertSame('unknown', $result['status']);
        $this->assertNull($result['risk_score']);
        $this->assertFalse($result['html_analyzed']);
    }
}