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
}
