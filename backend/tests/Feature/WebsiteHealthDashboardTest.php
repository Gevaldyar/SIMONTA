<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function createWebsite(
        string $name,
        bool $websiteActive = true,
        bool $opdActive = true
    ): Website {
        $opd = Opd::create([
            'name' => "OPD {$name}",
            'is_active' => $opdActive,
        ]);

        return $opd->websites()->create([
            'name' => $name,
            'url' => 'https://example.com',
            'is_active' => $websiteActive,
            'monitoring_enabled' => true,
            'monitoring_interval_minutes' => 10,
        ]);
    }

    private function createScan(
        Website $website,
        string $status,
        ?int $httpStatus,
        $createdAt,
        ?int $responseTime
    ): Scan {
        $terminal = in_array(
            $status,
            ['completed', 'failed'],
            true
        );

        $scan = $website->scans()->create([
            'status' => $status,
            'scheduled_for' => $createdAt,
            'started_at' => $status === 'queued'
                ? null
                : $createdAt,
            'completed_at' => $terminal
                ? $createdAt
                : null,
            'http_status_code' => $httpStatus,
            'response_time_ms' => $responseTime,
        ]);

        // Atur waktu pembuatan agar dapat menguji
        // jendela uptime 24 jam dan 7 hari.
        $scan->forceFill([
            'created_at' => $createdAt,
        ])->save();

        return $scan;
    }

    public function test_guest_cannot_view_website_health(): void
    {
        $this->getJson('/api/dashboard/website-health')
            ->assertUnauthorized();
    }

    public function test_admin_can_view_website_health_and_uptime(): void
    {
        $this->loginAsAdmin();

        $uptimeWebsite = $this->createWebsite(
            'Uptime Website'
        );

        $this->createScan(
            $uptimeWebsite,
            'completed',
            200,
            now()->subHours(2)->startOfSecond(),
            120
        );

        $this->createScan(
            $uptimeWebsite,
            'completed',
            404,
            now()->subHours(6)->startOfSecond(),
            240
        );

        $this->createScan(
            $uptimeWebsite,
            'failed',
            null,
            now()->subDays(6)->startOfSecond(),
            null
        );

        $onlineWebsite = $this->createWebsite(
            'Online Website'
        );

        $this->createScan(
            $onlineWebsite,
            'completed',
            200,
            now()->subHour()->startOfSecond(),
            100
        );

        $degradedWebsite = $this->createWebsite(
            'Degraded Website'
        );

        $this->createScan(
            $degradedWebsite,
            'completed',
            404,
            now()->subHour()->startOfSecond(),
            300
        );

        $checkingWebsite = $this->createWebsite(
            'Checking Website'
        );

        $this->createScan(
            $checkingWebsite,
            'queued',
            null,
            now()->startOfSecond(),
            null
        );

        $this->createWebsite(
            'Unknown Website'
        );

        // Website tidak aktif tidak boleh muncul.
        $this->createWebsite(
            'Inactive Website',
            false,
            true
        );

        // Website milik OPD tidak aktif juga dikecualikan.
        $this->createWebsite(
            'Inactive OPD Website',
            true,
            false
        );

        $response = $this->getJson(
            '/api/dashboard/website-health'
        );

        $response->assertOk()
            ->assertJsonPath(
                'data.summary.total_websites',
                5
            )
            ->assertJsonPath('data.summary.online', 2)
            ->assertJsonPath('data.summary.degraded', 1)
            ->assertJsonPath('data.summary.down', 0)
            ->assertJsonPath('data.summary.checking', 1)
            ->assertJsonPath('data.summary.unknown', 1)
            ->assertJsonPath(
                'data.summary.uptime_24h_percent',
                50
            )
            ->assertJsonPath(
                'data.summary.uptime_7d_percent',
                40
            )
            ->assertJsonPath(
                'data.summary.average_response_time_7d_ms',
                190
            );

        $websites = collect(
            $response->json('data.websites')
        )->keyBy('name');

        $this->assertSame(
            200,
            $websites['Uptime Website']['last_scan']['http_status_code']
        );

        $this->assertSame(
            'online',
            $websites['Online Website']['health_status']
        );

        $this->assertSame(
            'degraded',
            $websites['Degraded Website']['health_status']
        );

        $this->assertSame(
            'checking',
            $websites['Checking Website']['health_status']
        );

        $this->assertSame(
            'unknown',
            $websites['Unknown Website']['health_status']
        );

        // Uptime khusus website Uptime Website:
        // 1 sukses dari 2 scan dalam 24 jam.
        $this->assertEquals(
            50,
            $websites['Uptime Website']['uptime_24h_percent']
        );

        // 1 sukses dari 3 scan dalam 7 hari.
        $this->assertEquals(
            33.33,
            $websites['Uptime Website']['uptime_7d_percent']
        );

        $this->assertEquals(
            180,
            $websites['Uptime Website']['average_response_time_7d_ms']
        );

        $this->assertArrayNotHasKey(
            'Inactive Website',
            $websites->all()
        );

        $this->assertArrayNotHasKey(
            'Inactive OPD Website',
            $websites->all()
        );
    }

    public function test_uptime_is_null_when_no_terminal_scans_exist(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite(
            'Website Belum Diperiksa'
        );

        $this->createScan(
            $website,
            'queued',
            null,
            now(),
            null
        );

        $response = $this->getJson(
            '/api/dashboard/website-health'
        );

        $response->assertOk()
            ->assertJsonPath(
                'data.websites.0.health_status',
                'checking'
            )
            ->assertJsonPath(
                'data.websites.0.uptime_24h_percent',
                null
            )
            ->assertJsonPath(
                'data.websites.0.uptime_7d_percent',
                null
            );
    }

    public function test_empty_website_registry_returns_empty_health_summary(): void
    {
        $this->loginAsAdmin();

        $this->getJson('/api/dashboard/website-health')
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_websites',
                0
            )
            ->assertJsonPath(
                'data.summary.uptime_24h_percent',
                null
            )
            ->assertJsonPath(
                'data.websites',
                []
            );
    }
    public function test_health_status_supports_redirect_server_error_and_running_scans(): void
{
    $this->loginAsAdmin();

    // HTTP 301 harus dianggap Online.
    $redirectWebsite = $this->createWebsite(
        'Redirect Website'
    );

    $this->createScan(
        $redirectWebsite,
        'completed',
        301,
        now()->subMinutes(5)->startOfSecond(),
        85
    );

    // HTTP 503 harus dianggap Down.
    $serverErrorWebsite = $this->createWebsite(
        'Server Error Website'
    );

    $this->createScan(
        $serverErrorWebsite,
        'completed',
        503,
        now()->subMinutes(5)->startOfSecond(),
        250
    );

    // Scan dengan status running harus dianggap Checking.
    $runningWebsite = $this->createWebsite(
        'Running Website'
    );

    $this->createScan(
        $runningWebsite,
        'running',
        null,
        now()->startOfSecond(),
        null
    );

    $response = $this->getJson(
        '/api/dashboard/website-health'
    );

    $response->assertOk()
        ->assertJsonPath(
            'data.summary.total_websites',
            3
        )
        ->assertJsonPath(
            'data.summary.online',
            1
        )
        ->assertJsonPath(
            'data.summary.down',
            1
        )
        ->assertJsonPath(
            'data.summary.checking',
            1
        );

    $websites = collect(
        $response->json('data.websites')
    )->keyBy('name');

    $this->assertSame(
        'online',
        $websites['Redirect Website']['health_status']
    );

    $this->assertSame(
        'down',
        $websites['Server Error Website']['health_status']
    );

    $this->assertSame(
        'checking',
        $websites['Running Website']['health_status']
    );
}
}
