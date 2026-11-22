<?php

namespace Tests\Feature;

use App\Services\DomainRdapService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainRdapServiceTest extends TestCase
{
    private const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';

    private const RDAP_SERVER = 'https://rdap.pandi.id/rdap';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('simonta:rdap:dns-bootstrap:v1');
    }

    private function bootstrapResponse(
        array $suffixes = ['id'],
        array $servers = [self::RDAP_SERVER . '/']
    ): array {
        return [
            'version' => '1.0',
            'services' => [
                [
                    $suffixes,
                    $servers,
                ],
            ],
        ];
    }

    private function domainResponse(int $expiryDays = 60): array
    {
        return [
            'objectClassName' => 'domain',
            'handle' => 'EXAMPLE-ID',
            'ldhName' => 'example.id',

            'events' => [
                [
                    'eventAction' => 'registration',
                    'eventDate' => now()
                        ->subYears(2)
                        ->toIso8601String(),
                ],
                [
                    'eventAction' => 'expiration',
                    'eventDate' => now()
                        ->addDays($expiryDays)
                        ->endOfDay()
                        ->toIso8601String(),
                ],
            ],

            'entities' => [
                [
                    'roles' => ['registrar'],
                    'vcardArray' => [
                        'vcard',
                        [
                            ['version', [], 'text', '4.0'],
                            ['fn', [], 'text', 'Example Registrar'],
                        ],
                    ],
                ],
            ],

            'nameservers' => [
                ['ldhName' => 'ns1.example.id'],
                ['ldhName' => 'ns2.example.id'],
            ],

            'status' => ['active'],
        ];
    }

    public function test_it_reads_domain_registration_from_iana_rdap(): void
    {
        Http::fake([
            self::BOOTSTRAP_URL => Http::response(
                $this->bootstrapResponse(),
                200
            ),

            'rdap.pandi.id/rdap/domain/*' => Http::response(
                $this->domainResponse(60),
                200
            ),
        ]);

        $result = app(DomainRdapService::class)
            ->inspect('https://example.id/beranda');

        $this->assertSame('example.id', $result['domain']);
        $this->assertSame('valid', $result['status']);
        $this->assertSame('Example Registrar', $result['registrar']);

        $this->assertNotNull($result['registered_at']);
        $this->assertNotNull($result['expires_at']);

        $this->assertGreaterThan(
            30,
            $result['days_remaining']
        );

        $this->assertSame(
            'ns1.example.id',
            $result['nameservers'][0]
        );

        $this->assertSame(
            self::RDAP_SERVER,
            $result['rdap_server']
        );

        Http::assertSentCount(2);
    }

    public function test_it_finds_parent_domain_for_subdomain(): void
    {
        Http::fake([
            self::BOOTSTRAP_URL => Http::response(
                $this->bootstrapResponse(),
                200
            ),

            'rdap.pandi.id/rdap/domain/*' => Http::sequence()
                ->push(['errorCode' => 404], 404)
                ->push($this->domainResponse(60), 200),
        ]);

        $result = app(DomainRdapService::class)
            ->inspect('https://portal.example.id/dashboard');

        $this->assertSame('example.id', $result['domain']);
        $this->assertSame('valid', $result['status']);

        Http::assertSentCount(3);
    }

    public function test_it_marks_domain_expiring_soon(): void
    {
        Http::fake([
            self::BOOTSTRAP_URL => Http::response(
                $this->bootstrapResponse(),
                200
            ),

            'rdap.pandi.id/rdap/domain/*' => Http::response(
                $this->domainResponse(10),
                200
            ),
        ]);

        $result = app(DomainRdapService::class)
            ->inspect('https://example.id');

        $this->assertSame('expiring_soon', $result['status']);
        $this->assertGreaterThanOrEqual(0, $result['days_remaining']);
        $this->assertLessThanOrEqual(30, $result['days_remaining']);
    }

    public function test_it_returns_unavailable_when_expiration_is_missing(): void
    {
        $response = $this->domainResponse();

        $response['events'] = [
            [
                'eventAction' => 'registration',
                'eventDate' => now()->subYear()->toIso8601String(),
            ],
        ];

        Http::fake([
            self::BOOTSTRAP_URL => Http::response(
                $this->bootstrapResponse(),
                200
            ),

            'rdap.pandi.id/rdap/domain/*' => Http::response(
                $response,
                200
            ),
        ]);

        $result = app(DomainRdapService::class)
            ->inspect('https://example.id');

        $this->assertSame('unavailable', $result['status']);
        $this->assertNull($result['expires_at']);
        $this->assertNull($result['days_remaining']);
    }

    public function test_it_returns_not_found_for_unregistered_domain(): void
    {
        Http::fake([
            self::BOOTSTRAP_URL => Http::response(
                $this->bootstrapResponse(),
                200
            ),

            'rdap.pandi.id/rdap/domain/*' => Http::response(
                ['errorCode' => 404],
                404
            ),
        ]);

        $result = app(DomainRdapService::class)
            ->inspect('https://missing-domain.id');

        $this->assertSame('not_found', $result['status']);
        $this->assertSame('missing-domain.id', $result['domain']);
        $this->assertNull($result['expires_at']);
    }

    public function test_it_returns_unsupported_when_rdaps_server_is_not_listed(): void
    {
        Http::fake([
            self::BOOTSTRAP_URL => Http::response(
                $this->bootstrapResponse(
                    ['id'],
                    [self::RDAP_SERVER . '/']
                ),
                200
            ),
        ]);

        $result = app(DomainRdapService::class)
            ->inspect('https://example.unknownextension');

        $this->assertSame('unsupported', $result['status']);
        $this->assertNull($result['expires_at']);

        Http::assertSentCount(1);
    }

    public function test_it_rejects_ip_address_as_domain(): void
    {
        Http::fake();

        $result = app(DomainRdapService::class)
            ->inspect('https://8.8.8.8');

        $this->assertSame('unsupported', $result['status']);

        Http::assertNothingSent();
    }
}
