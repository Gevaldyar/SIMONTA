<?php

namespace App\Services;

use DOMDocument;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;
use LengthException;
use RuntimeException;
use Throwable;

class HttpInspector
{
    private const MAX_BODY_BYTES = 1048576; // 1 MB
    private const MAX_HEADER_BYTES = 32768; // 32 KB
    private const MAX_HEADER_COUNT = 100;

    private const MAX_REDIRECTS = 5;
    private const MAX_TOTAL_TIME_MS = 30000;

    private const CONNECT_TIMEOUT_MS = 5000;
    private const REQUEST_TIMEOUT_MS = 15000;

    public function __construct(
        private readonly PublicUrlValidator $urlValidator
    ) {}

    /**
     * Mengakses website dan menghasilkan fingerprint.
     *
     * Semua redirect divalidasi kembali sebelum diakses.
     */
    public function inspect(string $url): array
    {
        $startedAt = hrtime(true);

        $originalUrl = trim($url);
        $currentUrl = $originalUrl;

        $visited = [];
        $redirectCount = 0;

        while (true) {
            // Validasi setiap URL sebelum melakukan request.
            $validated = $this->urlValidator->validate(
                $currentUrl
            );

            $currentUrl = $validated['url'];

            // Mencegah redirect loop.
            if (isset($visited[$currentUrl])) {
                throw new RuntimeException(
                    'Redirect loop terdeteksi.'
                );
            }

            $visited[$currentUrl] = true;

            // Seluruh redirect menggunakan satu batas waktu total.
            $elapsedMs = (int) (
                (hrtime(true) - $startedAt) / 1000000
            );

            $remainingMs = self::MAX_TOTAL_TIME_MS - $elapsedMs;

            if ($remainingMs <= 0) {
                throw new RuntimeException(
                    'Batas waktu pemeriksaan website terlampaui.'
                );
            }

            $response = $this->performRequest(
                $currentUrl,
                $validated,
                $remainingMs
            );

            $statusCode = $response['status_code'];
            $headers = $response['headers'];

            // Redirect ditangani secara manual.
            $redirectStatuses = [301, 302, 303, 307, 308];

            $location = $this->firstHeader(
                $headers,
                'location'
            );

            if (
                in_array($statusCode, $redirectStatuses, true)
                && $location !== null
            ) {
                if ($redirectCount >= self::MAX_REDIRECTS) {
                    throw new RuntimeException(
                        'Jumlah redirect melebihi batas.'
                    );
                }

                $nextUrl = $this->resolveRedirect(
                    $currentUrl,
                    $location
                );

                // Mencegah downgrade HTTPS menjadi HTTP.
                $currentScheme = strtolower(
                    (string) parse_url(
                        $currentUrl,
                        PHP_URL_SCHEME
                    )
                );

                $nextScheme = strtolower(
                    (string) parse_url(
                        $nextUrl,
                        PHP_URL_SCHEME
                    )
                );

                if (
                    $currentScheme === 'https'
                    && $nextScheme === 'http'
                ) {
                    throw new InvalidArgumentException(
                        'Redirect dari HTTPS ke HTTP ditolak.'
                    );
                }

                $currentUrl = $nextUrl;
                $redirectCount++;

                continue;
            }

            break;
        }

        // Jangan menerima respons yang menggunakan kompresi
        // yang tidak kita minta.
        $contentEncoding = strtolower(
            $this->firstHeader($headers, 'content-encoding') ?? ''
        );

        if (
            $contentEncoding !== ''
            && $contentEncoding !== 'identity'
        ) {
            throw new RuntimeException(
                'Content-Encoding respons tidak didukung.'
            );
        }

        $contentType = strtolower(
            $this->firstHeader($headers, 'content-type') ?? ''
        );

        $isHtml =
            str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/xhtml+xml');

        // Hanya HTML yang diteruskan ke HTML analyzer.
        $html = $isHtml ? $response['body'] : '';

        $parsedHtml = $isHtml
            ? $this->parseHtml($html)
            : [
                'page_title' => null,
                'meta' => [],
                'scripts' => [],
            ];

        $elapsedMs = (int) (
            (hrtime(true) - $startedAt) / 1000000
        );

        return [
            // Data untuk TechnologyDetectionService.
            'url' => $originalUrl,
            'final_url' => $currentUrl,

            'headers' => $this->filterHeaders($headers),
            'html' => $html,
            'cookies' => $this->extractCookieNames($headers),

            'meta' => $parsedHtml['meta'],
            'scripts' => $parsedHtml['scripts'],
            'page_title' => $parsedHtml['page_title'],

            // Data untuk tabel scans.
            'status_code' => $statusCode,
            'response_time_ms' => $elapsedMs,
            'content_type' => $contentType,
            'body_size_bytes' => strlen($response['body']),
            'redirect_count' => $redirectCount,
        ];
    }

    /**
     * Melakukan satu HTTP GET menggunakan IP hasil validasi DNS.
     *
     * Method protected agar dapat diuji tanpa mengakses internet.
     */
    protected function performRequest(
        string $url,
        array $validated,
        int $remainingMs
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'Ekstensi PHP cURL tidak tersedia.'
            );
        }

        $host = $validated['host'];
        $port = $validated['port'];

        // Gunakan IP yang sudah diperiksa oleh PublicUrlValidator.
        $ip = $validated['addresses'][0];

        $responseHeaders = [];
        $responseBody = '';

        $statusCode = 0;

        $bodyTooLarge = false;
        $headersTooLarge = false;

        $headerBytes = 0;
        $headerCount = 0;

        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException(
                'Tidak dapat memulai koneksi cURL.'
            );
        }

        $timeoutMs = min(
            self::REQUEST_TIMEOUT_MS,
            $remainingMs
        );

        $connectTimeoutMs = min(
            self::CONNECT_TIMEOUT_MS,
            $timeoutMs
        );

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,

            // Redirect tidak boleh diikuti otomatis oleh cURL.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,

            CURLOPT_HTTPGET => true,

            CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_NOSIGNAL => true,

            // Sertifikat TLS dan hostname wajib diverifikasi.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_USERAGENT => 'SIMONTA-TechnologyMonitor/1.0',

            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
                'Accept-Encoding: identity',
                'Connection: close',
            ],

            // Jangan melewati proxy lingkungan server.
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',

            // Paksa keluarga IP sesuai alamat yang dipilih.
            CURLOPT_IPRESOLVE => filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6
            ) ? CURL_IPRESOLVE_V6 : CURL_IPRESOLVE_V4,

            // Batasi protokol yang boleh digunakan.
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,

            // Batas ukuran dan jumlah header respons.
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (
                &$responseHeaders,
                &$statusCode,
                &$headerBytes,
                &$headerCount,
                &$headersTooLarge,
                &$bodyTooLarge
            ): int {
                $lineLength = strlen($line);

                $headerBytes += $lineLength;
                $headerCount++;

                if (
                    $headerBytes > self::MAX_HEADER_BYTES
                    || $headerCount > self::MAX_HEADER_COUNT
                ) {
                    $headersTooLarge = true;
                    return 0;
                }

                $trimmed = trim($line);

                if ($trimmed === '') {
                    return $lineLength;
                }

                // Awal blok respons HTTP baru.
                if (
                    preg_match(
                        '/^HTTP\/\S+\s+(\d{3})/i',
                        $trimmed,
                        $matches
                    )
                ) {
                    $statusCode = (int) $matches[1];
                    $responseHeaders = [];

                    return $lineLength;
                }

                $separator = strpos($trimmed, ':');

                if ($separator === false) {
                    return $lineLength;
                }

                $name = strtolower(
                    trim(substr($trimmed, 0, $separator))
                );

                $value = trim(
                    substr($trimmed, $separator + 1)
                );

                if ($name === '') {
                    return $lineLength;
                }

                if ($name === 'content-length' && ctype_digit($value)) {
                    if ((int) $value > self::MAX_BODY_BYTES) {
                        $bodyTooLarge = true;
                        return 0;
                    }
                }

                $responseHeaders[$name][] = $value;

                return $lineLength;
            },

            // Membatasi data yang benar-benar diterima,
            // termasuk jika server tidak mengirim Content-Length.
            CURLOPT_WRITEFUNCTION => function (
                $curl,
                string $chunk
            ) use (&$responseBody, &$bodyTooLarge): int {
                $chunkLength = strlen($chunk);

                if (
                    strlen($responseBody) + $chunkLength
                    > self::MAX_BODY_BYTES
                ) {
                    $bodyTooLarge = true;
                    return 0;
                }

                $responseBody .= $chunk;

                return $chunkLength;
            },
        ];

        // DNS pinning:
        // hostname tetap digunakan pada URL agar Host header dan
        // verifikasi TLS tetap mengacu pada domain aslinya.
        // Koneksi diarahkan ke IP publik yang sudah divalidasi.
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $resolvedAddress = str_contains($ip, ':')
                ? '[' . $ip . ']'
                : $ip;

            $options[CURLOPT_RESOLVE] = [
                $host . ':' . $port . ':' . $resolvedAddress,
            ];
        }

        try {
            if (!curl_setopt_array($handle, $options)) {
                throw new RuntimeException(
                    'Konfigurasi koneksi cURL gagal.'
                );
            }

            $success = curl_exec($handle);

            $curlError = curl_error($handle);
            $statusInfo = (int) curl_getinfo(
                $handle,
                CURLINFO_RESPONSE_CODE
            );

        } finally {
            curl_close($handle);
        }

        if ($headersTooLarge) {
            throw new LengthException(
                'Ukuran header respons melebihi batas.'
            );
        }

        if ($bodyTooLarge) {
            throw new LengthException(
                'Ukuran respons website melebihi batas 1 MB.'
            );
        }

        if ($success === false) {
            throw new RuntimeException(
                'Request website gagal: ' . $curlError
            );
        }

        if ($statusInfo === 0) {
            throw new RuntimeException(
                'Website tidak memberikan status HTTP yang valid.'
            );
        }

        return [
            'status_code' => $statusInfo ?: $statusCode,
            'headers' => $responseHeaders,
            'body' => $responseBody,
        ];
    }

    /**
     * Menyelesaikan URL redirect relatif maupun absolut.
     */
    private function resolveRedirect(
        string $currentUrl,
        string $location
    ): string {
        $location = trim($location);

        if (
            $location === ''
            || strlen($location) > 2048
            || preg_match('/[\x00-\x20\x7F]/', $location)
        ) {
            throw new InvalidArgumentException(
                'Lokasi redirect tidak valid.'
            );
        }

        try {
            $resolved = UriResolver::resolve(
                new Uri($currentUrl),
                new Uri($location)
            );

            $url = (string) $resolved;

        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'Tidak dapat memproses lokasi redirect.',
                previous: $exception
            );
        }

        // Fragment tidak dikirim dalam HTTP request.
        $url = preg_replace('/#.*$/s', '', $url);

        if (!is_string($url) || $url === '') {
            throw new InvalidArgumentException(
                'URL redirect tidak valid.'
            );
        }

        return $url;
    }

    /**
     * Mengambil elemen penting dari HTML.
     *
     * LIBXML_NONET mencegah parser melakukan akses jaringan.
     */
    private function parseHtml(string $html): array
    {
        $title = null;
        $meta = [];
        $scripts = [];

        $previousLibxmlSetting = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();

            $loaded = @$dom->loadHTML(
                $html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            if ($loaded) {
                $titleNodes = $dom->getElementsByTagName('title');

                if ($titleNodes->length > 0) {
                    $title = substr(
                        trim($titleNodes->item(0)->textContent),
                        0,
                        500
                    );
                }

                foreach ($dom->getElementsByTagName('meta') as $node) {
                    if (count($meta) >= 100) {
                        break;
                    }

                    $name = strtolower(
                        trim(
                            $node->getAttribute('name')
                            ?: $node->getAttribute('property')
                            ?: $node->getAttribute('http-equiv')
                        )
                    );

                    $content = trim(
                        $node->getAttribute('content')
                    );

                    if ($name === '' || $content === '') {
                        continue;
                    }

                    $meta[] = [
                        'name' => substr($name, 0, 150),
                        'content' => substr($content, 0, 1000),
                    ];
                }

                foreach ($dom->getElementsByTagName('script') as $node) {
                    if (count($scripts) >= 100) {
                        break;
                    }

                    $src = trim($node->getAttribute('src'));

                    if ($src !== '') {
                        $scripts[] = substr($src, 0, 2048);
                    }
                }
            }

        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlSetting);
        }

        return [
            'page_title' => $title,
            'meta' => $meta,
            'scripts' => array_values(array_unique($scripts)),
        ];
    }

    /**
     * Mengambil nama cookie saja, tidak menyimpan nilainya.
     */
    private function extractCookieNames(array $headers): array
    {
        $cookies = $headers['set-cookie'] ?? [];

        $names = [];

        foreach ($cookies as $cookie) {
            $firstPart = trim(explode(';', $cookie, 2)[0]);

            $separator = strpos($firstPart, '=');

            if ($separator === false) {
                continue;
            }

            $name = trim(
                substr($firstPart, 0, $separator)
            );

            if (
                $name !== ''
                && preg_match(
                    "/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/",
                    $name
                )
            ) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Hanya mengembalikan header yang relevan untuk fingerprint.
     *
     * Set-Cookie sengaja tidak disertakan.
     */
    private function filterHeaders(array $headers): array
    {
        $allowed = [
            'content-type',
            'server',
            'x-powered-by',
            'x-aspnet-version',
            'x-aspnetmvc-version',
            'x-generator',
            'x-drupal-cache',
            'x-redirect-by',
            'x-cache',
            'x-cache-hits',
            'x-served-by',
            'via',
            'cf-cache-status',
        ];

        $filtered = [];

        foreach ($allowed as $name) {
            if (!isset($headers[$name])) {
                continue;
            }

            $filtered[$name] = array_map(
                fn ($value) => substr($value, 0, 1000),
                $headers[$name]
            );
        }

        return $filtered;
    }

    /**
     * Mengambil nilai pertama dari header.
     */
    private function firstHeader(
        array $headers,
        string $name
    ): ?string {
        $values = $headers[strtolower($name)] ?? [];

        if (empty($values)) {
            return null;
        }

        return (string) $values[0];
    }
}
