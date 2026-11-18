<?php

namespace Tests\Feature;

use App\Services\HttpInspector;
use App\Services\PublicUrlValidator;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class HttpInspectorTest extends TestCase
{
    private function createInspector(array $responses): HttpInspector
    {
        $validator = new class extends PublicUrlValidator {
            protected function resolveHost(string $host): array
            {
                return ['93.184.216.34'];
            }
        };

        return new class($validator, $responses) extends HttpInspector {
            private array $responses;

            public array $requestedUrls = [];

            public function __construct(
                PublicUrlValidator $validator,
                array $responses
            ) {
                parent::__construct($validator);

                $this->responses = $responses;
            }

            /**
             * Menggantikan koneksi jaringan dengan respons simulasi.
             */
            protected function performRequest(
                string $url,
                array $validated,
                int $remainingMs
            ): array {
                $this->requestedUrls[] = $url;

                $response = array_shift($this->responses);

                if (!is_array($response)) {
                    throw new RuntimeException(
                        'Tidak ada respons simulasi yang tersedia.'
                    );
                }

                return $response;
            }
        };
    }

    public function test_inspector_extracts_html_fingerprints(): void
    {
        $inspector = $this->createInspector([
            [
                'status_code' => 200,

                'headers' => [
                    'content-type' => [
                        'text/html; charset=utf-8'
                    ],

                    'x-powered-by' => [
                        'PHP/8.3.2'
                    ],

                    'set-cookie' => [
                        'PHPSESSID=secret-value; Path=/; HttpOnly',
                        'laravel_session=another-secret; Path=/; Secure',
                    ],
                ],

                'body' => '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Portal OPD Tasikmalaya</title>
                        <meta name="generator"
                            content="WordPress 6.6.1">

                        <script src="/assets/app.js"></script>
                    </head>
                    <body>
                        <h1>Selamat Datang</h1>
                    </body>
                    </html>
                ',
            ],
        ]);

        $result = $inspector->inspect(
            'https://example.com'
        );

        // Status HTTP berhasil didapatkan.
        $this->assertSame(200, $result['status_code']);

        // HTML berhasil dibaca.
        $this->assertNotEmpty($result['html']);

        // Page title berhasil diekstrak.
        $this->assertSame(
            'Portal OPD Tasikmalaya',
            $result['page_title']
        );

        // Meta generator berhasil ditemukan.
        $this->assertSame(
            'generator',
            $result['meta'][0]['name']
        );

        $this->assertSame(
            'WordPress 6.6.1',
            $result['meta'][0]['content']
        );

        // Script berhasil ditemukan.
        $this->assertContains(
            '/assets/app.js',
            $result['scripts']
        );

        // Cookie hanya berisi nama.
        $this->assertContains(
            'PHPSESSID',
            $result['cookies']
        );

        $this->assertContains(
            'laravel_session',
            $result['cookies']
        );

        // Nilai cookie rahasia tidak disertakan.
        $this->assertStringNotContainsString(
            'secret-value',
            json_encode($result)
        );

        $this->assertStringNotContainsString(
            'another-secret',
            json_encode($result)
        );

        // Header X-Powered-By tersedia untuk engine.
        $this->assertSame(
            ['PHP/8.3.2'],
            $result['headers']['x-powered-by']
        );

        // Set-Cookie tidak boleh masuk ke hasil headers.
        $this->assertArrayNotHasKey(
            'set-cookie',
            $result['headers']
        );
    }

    public function test_inspector_follows_and_validates_redirect(): void
    {
        $inspector = $this->createInspector([
            [
                'status_code' => 302,

                'headers' => [
                    'location' => [
                        'https://www.example.com/portal'
                    ],
                ],

                'body' => '',
            ],

            [
                'status_code' => 200,

                'headers' => [
                    'content-type' => [
                        'text/html'
                    ],
                ],

                'body' => '<html><head><title>Portal</title></head></html>',
            ],
        ]);

        $result = $inspector->inspect(
            'https://example.com'
        );

        $this->assertSame(200, $result['status_code']);

        $this->assertSame(
            'https://www.example.com/portal',
            $result['final_url']
        );

        $this->assertSame(1, $result['redirect_count']);

        $this->assertCount(
            2,
            $inspector->requestedUrls
        );
    }

    public function test_private_ip_redirect_is_rejected(): void
    {
        $inspector = $this->createInspector([
            [
                'status_code' => 302,

                'headers' => [
                    'location' => [
                        'http://127.0.0.1/admin'
                    ],
                ],

                'body' => '',
            ],
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $inspector->inspect(
            'https://example.com'
        );
    }

    public function test_https_to_http_redirect_is_rejected(): void
    {
        $inspector = $this->createInspector([
            [
                'status_code' => 301,

                'headers' => [
                    'location' => [
                        'http://example.com/portal'
                    ],
                ],

                'body' => '',
            ],
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $inspector->inspect(
            'https://example.com'
        );
    }

    public function test_redirect_loop_is_rejected(): void
    {
        $inspector = $this->createInspector([
            [
                'status_code' => 302,
                'headers' => [
                    'location' => [
                        'https://example.com'
                    ],
                ],
                'body' => '',
            ],
        ]);

        $this->expectException(RuntimeException::class);

        $inspector->inspect(
            'https://example.com'
        );
    }
}
