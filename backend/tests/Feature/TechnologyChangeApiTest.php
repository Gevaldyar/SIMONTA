<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\Technology;
use App\Models\TechnologyChange;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TechnologyChangeApiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function createWebsite(
        string $opdName,
        string $websiteName,
        string $url
    ): Website {
        $opd = Opd::create([
            'name' => $opdName,
        ]);

        return $opd->websites()->create([
            'name' => $websiteName,
            'url' => $url,
        ]);
    }

    private function createTechnology(
        string $name,
        string $slug
    ): Technology {
        return Technology::create([
            'name' => $name,
            'slug' => $slug,
            'category' => 'backend_framework',
            'is_active' => true,
        ]);
    }

    /**
     * Membuat data perubahan teknologi beserta scan
     * terbaru dan scan sebelumnya.
     */
    private function createChange(
        Website $website,
        Technology $technology,
        string $changeType,
        string $createdAt,
        ?string $previousVersion = null,
        ?string $currentVersion = null
    ): TechnologyChange {
        $time = Carbon::parse($createdAt);

        $previousScan = $website->scans()->create([
            'status' => 'completed',
            'started_at' => $time->copy()->subMinutes(10),
            'completed_at' => $time->copy()->subMinutes(9),
            'http_status_code' => 200,
        ]);

        $currentScan = $website->scans()->create([
            'status' => 'completed',
            'started_at' => $time->copy()->subMinutes(2),
            'completed_at' => $time,
            'http_status_code' => 200,
        ]);

        $change = TechnologyChange::create([
    'scan_id' => $currentScan->id,
    'previous_scan_id' => $previousScan->id,

    'technology_id' => $technology->id,
    'technology_name' => $technology->name,
    'technology_slug' => $technology->slug,

    'change_type' => $changeType,

    'previous_version' => $previousVersion,
    'previous_confidence' => $previousVersion !== null
        ? 90
        : null,
    'previous_evidence' => $previousVersion !== null
        ? [['source' => 'previous_scan']]
        : null,

    'current_version' => $currentVersion,
    'current_confidence' => $currentVersion !== null
        ? 95
        : null,
    'current_evidence' => $currentVersion !== null
        ? [['source' => 'current_scan']]
        : null,
]);

// Atur timestamp secara eksplisit untuk kebutuhan pengujian.
// forceFill digunakan karena created_at dan updated_at
// tidak perlu dimasukkan ke daftar fillable model.
$change->forceFill([
    'created_at' => $time,
    'updated_at' => $time,
])->save();

return $change;
    }

    public function test_guest_cannot_access_technology_change_api(): void
{
    $this->getJson('/api/technology-changes')
        ->assertUnauthorized();

    $this->getJson('/api/technology-changes/summary')
        ->assertUnauthorized();

    $this->getJson('/api/technology-changes/1')
        ->assertUnauthorized();
}

public function test_admin_can_view_technology_change_list(): void
{
    $this->loginAsAdmin();

    $website = $this->createWebsite(
        'Dinas Contoh',
        'Website Utama',
        'https://example.com'
    );

    $php = $this->createTechnology('PHP', 'php');
    $nginx = $this->createTechnology('Nginx', 'nginx');

    $this->createChange(
        $website,
        $php,
        'version_changed',
        '2026-11-17 10:00:00',
        '8.2.0',
        '8.3.2'
    );

    $this->createChange(
        $website,
        $nginx,
        'added',
        '2026-11-18 10:00:00',
        null,
        '1.26.0'
    );

    $this->getJson('/api/technology-changes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.change_type', 'added')
        ->assertJsonPath('data.0.technology_name', 'Nginx');
}

    public function test_admin_can_filter_changes_by_opd_website_type_and_date(): void
    {
        $this->loginAsAdmin();

        $websiteA = $this->createWebsite(
            'Dinas Sosial',
            'Website Dinsos',
            'https://dinsos.example.com'
        );

        $websiteB = $this->createWebsite(
            'Dinas Kesehatan',
            'Website Dinkes',
            'https://dinkes.example.com'
        );

        $php = $this->createTechnology('PHP', 'php');
        $laravel = $this->createTechnology('Laravel', 'laravel');

        $this->createChange(
            $websiteA,
            $php,
            'added',
            '2026-11-17 10:00:00',
            null,
            '8.3.2'
        );

        $this->createChange(
            $websiteA,
            $laravel,
            'removed',
            '2026-11-18 10:00:00',
            '11.x',
            null
        );

        $this->createChange(
            $websiteB,
            $php,
            'added',
            '2026-11-18 11:00:00',
            null,
            '8.3.2'
        );

        $opdId = $websiteA->opd_id;

        $this->getJson(
            '/api/technology-changes?' . http_build_query([
                'opd_id' => $opdId,
                'website_id' => $websiteA->id,
                'change_type' => 'added',
                'date_from' => '2026-11-17',
                'date_to' => '2026-11-17',
            ])
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.technology_slug',
                'php'
            )
            ->assertJsonPath(
                'data.0.change_type',
                'added'
            );
    }

    public function test_admin_can_view_technology_change_summary(): void
    {
        $this->loginAsAdmin();

        $websiteA = $this->createWebsite(
            'Dinas Sosial',
            'Website Dinsos',
            'https://dinsos.example.com'
        );

        $websiteB = $this->createWebsite(
            'Dinas Kesehatan',
            'Website Dinkes',
            'https://dinkes.example.com'
        );

        $php = $this->createTechnology('PHP', 'php');
        $laravel = $this->createTechnology('Laravel', 'laravel');
        $nginx = $this->createTechnology('Nginx', 'nginx');

        $this->createChange(
            $websiteA,
            $php,
            'added',
            '2026-11-17 10:00:00',
            null,
            '8.3.2'
        );

        $this->createChange(
            $websiteA,
            $laravel,
            'removed',
            '2026-11-18 10:00:00',
            '11.x',
            null
        );

        $this->createChange(
            $websiteB,
            $nginx,
            'version_changed',
            '2026-11-18 11:00:00',
            '1.24.0',
            '1.26.0'
        );

        $this->getJson('/api/technology-changes/summary')
            ->assertOk()
            ->assertJsonPath('data.total_changes', 3)
            ->assertJsonPath('data.added', 1)
            ->assertJsonPath('data.removed', 1)
            ->assertJsonPath('data.version_changed', 1)
            ->assertJsonPath('data.affected_websites', 2);
    }

    public function test_admin_can_view_technology_change_detail(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite(
            'Dinas Contoh',
            'Website Utama',
            'https://example.com'
        );

        $php = $this->createTechnology('PHP', 'php');

        $change = $this->createChange(
            $website,
            $php,
            'version_changed',
            '2026-11-18 10:00:00',
            '8.2.0',
            '8.3.2'
        );

        $this->getJson(
            "/api/technology-changes/{$change->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.id', $change->id)
            ->assertJsonPath(
                'data.change_type',
                'version_changed'
            )
            ->assertJsonPath(
                'data.technology.name',
                'PHP'
            )
            ->assertJsonPath(
                'data.previous_version',
                '8.2.0'
            )
            ->assertJsonPath(
                'data.current_version',
                '8.3.2'
            )
            ->assertJsonPath(
                'data.scan.website.name',
                'Website Utama'
            )
            ->assertJsonPath(
                'data.scan.website.opd.name',
                'Dinas Contoh'
            )
            ->assertJsonPath(
                'data.previous_scan.id',
                $change->previous_scan_id
            );
    }

    public function test_invalid_technology_change_filters_are_rejected(): void
    {
        $this->loginAsAdmin();

        $this->getJson(
            '/api/technology-changes?change_type=unknown'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'change_type',
            ]);

        $this->getJson(
            '/api/technology-changes?date_from=2026-11-18&date_to=2026-11-17'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);

        $this->getJson(
            '/api/technology-changes?per_page=101'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }
}