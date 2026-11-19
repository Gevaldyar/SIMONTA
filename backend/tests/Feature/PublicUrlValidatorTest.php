<?php

namespace Tests\Feature;

use App\Services\PublicUrlValidator;
use InvalidArgumentException;
use Tests\TestCase;

class PublicUrlValidatorTest extends TestCase
{
    public function test_public_ip_address_is_allowed(): void
    {
        $validator = new PublicUrlValidator();

        $result = $validator->validate(
            'https://1.1.1.1/status#section'
        );

        $this->assertSame('https', $result['scheme']);
        $this->assertSame('1.1.1.1', $result['host']);
        $this->assertSame(443, $result['port']);

        $this->assertSame(
            ['1.1.1.1'],
            $result['addresses']
        );

        $this->assertSame(
            'https://1.1.1.1/status',
            $result['url']
        );
    }

    public function test_localhost_is_rejected(): void
    {
        $validator = new PublicUrlValidator();

        $this->expectException(InvalidArgumentException::class);

        $validator->validate('http://127.0.0.1');
    }

    public function test_private_ip_is_rejected(): void
    {
        $validator = new PublicUrlValidator();

        $this->expectException(InvalidArgumentException::class);

        $validator->validate('http://192.168.1.1');
    }

    public function test_unsupported_scheme_is_rejected(): void
    {
        $validator = new PublicUrlValidator();

        $this->expectException(InvalidArgumentException::class);

        $validator->validate('ftp://example.com');
    }

    public function test_url_credentials_are_rejected(): void
    {
        $validator = new PublicUrlValidator();

        $this->expectException(InvalidArgumentException::class);

        $validator->validate(
            'https://user:password@example.com'
        );
    }

    public function test_nonstandard_port_is_rejected(): void
    {
        $validator = new PublicUrlValidator();

        $this->expectException(InvalidArgumentException::class);

        $validator->validate('http://example.com:8080');
    }

    public function test_local_domain_is_rejected(): void
    {
        $validator = new PublicUrlValidator();

        $this->expectException(InvalidArgumentException::class);

        $validator->validate('http://server.local');
    }

    public function test_dns_with_private_ip_is_rejected(): void
    {
        // DNS disimulasikan agar tidak membutuhkan koneksi internet.
        $validator = new class extends PublicUrlValidator {
            protected function resolveHost(string $host): array
            {
                return [
                    '93.184.216.34',
                    '127.0.0.1',
                ];
            }
        };

        $this->expectException(InvalidArgumentException::class);

        $validator->validate('https://example.com');
    }

    public function test_public_domain_with_public_dns_is_allowed(): void
    {
        // DNS disimulasikan dengan IP publik.
        $validator = new class extends PublicUrlValidator {
            protected function resolveHost(string $host): array
            {
                return [
                    '93.184.216.34',
                ];
            }
        };

        $result = $validator->validate(
            'https://example.com'
        );

        $this->assertSame(
            'example.com',
            $result['host']
        );

        $this->assertSame(
            ['93.184.216.34'],
            $result['addresses']
        );
    }

public function test_public_ipv6_address_is_allowed(): void
{
    $validator = new PublicUrlValidator();

    $result = $validator->validate(
        'https://[2606:4700:4700::1111]'
    );

    $this->assertSame('https', $result['scheme']);

    $this->assertSame(
        '2606:4700:4700::1111',
        $result['host']
    );

    $this->assertSame(
        ['2606:4700:4700::1111'],
        $result['addresses']
    );
}

public function test_non_global_special_purpose_addresses_are_rejected(): void
{
    $validator = new PublicUrlValidator();

    $addresses = [
        // Carrier-grade NAT.
        '100.64.0.1',

        // IPv4 documentation ranges.
        '192.0.2.1',
        '198.51.100.1',
        '203.0.113.1',

        // Benchmarking and special addresses.
        '198.18.0.1',
        '224.0.0.1',
        '240.0.0.1',

        // IPv6 documentation.
        '2001:db8::1',
        
        '239.255.255.255',


        // IPv6 unique-local and link-local.
        'fc00::1',
        'fe80::1',

        'ff02::1',
    ];

    foreach ($addresses as $ip) {
        $host = str_contains($ip, ':')
            ? '[' . $ip . ']'
            : $ip;

        try {
            $validator->validate('https://' . $host);

            $this->fail(
                "Alamat IP {$ip} seharusnya ditolak."
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString(
                'privat atau tidak aman',
                $exception->getMessage()
            );
        }
    }
}

public function test_ipv4_mapped_ipv6_is_rejected(): void
{
    $validator = new PublicUrlValidator();

    $this->expectException(
        InvalidArgumentException::class
    );

    $validator->validate(
        'https://[::ffff:8.8.8.8]'
    );
}

}
