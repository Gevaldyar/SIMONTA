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
    ? $this->parseHtml($html, $contentType)
    : [
        'page_title' => null,
        'meta' => [],
        'scripts' => [],
    ];

        $elapsedMs = (int) (
            (hrtime(true) - $startedAt) / 1000000
        );

        return [
    'url' => $originalUrl,
    'final_url' => $currentUrl,

    'headers' => $this->filterHeaders($headers),

    // Header keamanan dipisahkan dari fingerprint teknologi.
    'security_headers' => $this->extractSecurityHeaders($headers),

    // Informasi sertifikat TLS.
    'tls_certificate' => $response['tls_certificate'] ?? null,

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
$certificateInfo = [];

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

            // Ambil informasi sertifikat untuk analisis.
            CURLOPT_CERTINFO => true,

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

// Informasi certificate chain dari koneksi HTTPS.
$certificateInfo = curl_getinfo(
    $handle,
    CURLINFO_CERTINFO
);

$certificateInfo = is_array($certificateInfo)
    ? $certificateInfo
    : [];

$statusInfo = (int) curl_getinfo(
    $handle,
    CURLINFO_RESPONSE_CODE
);

        } finally {
    // CurlHandle akan ditutup otomatis ketika referensinya dilepas.
    // curl_close() sudah deprecated pada PHP 8.5.
    unset($handle);
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

    'tls_certificate' => $this->summarizeTlsCertificate(
        $certificateInfo
    ),
];
    }

    /**
 * Mengambil informasi sertifikat TLS tanpa menyimpan
 * keseluruhan PEM certificate ke database.
 *
 * Informasi hanya ditandai verified apabila request HTTPS
 * berhasil melalui verifikasi TLS normal dari cURL.
 */
private function summarizeTlsCertificate(array $certificateInfo): ?array
{
    $leaf = $certificateInfo[0] ?? null;

    if (!is_array($leaf)) {
        return null;
    }

    $pem = $leaf['Cert'] ?? $leaf['Certificate'] ?? null;

    $parsed = is_string($pem)
        ? @openssl_x509_parse($pem)
        : false;

    $validFrom = is_array($parsed)
        ? ($parsed['validFrom_time_t'] ?? null)
        : null;

    $validTo = is_array($parsed)
        ? ($parsed['validTo_time_t'] ?? null)
        : null;

    // Fallback jika backend cURL menyediakan tanggal
    // tetapi tidak menyediakan PEM.
    if (!$validFrom) {
        $date = $leaf['Start date'] ?? null;
        $validFrom = is_string($date)
            ? strtotime($date)
            : false;
    }

    if (!$validTo) {
        $date = $leaf['Expire date'] ?? null;
        $validTo = is_string($date)
            ? strtotime($date)
            : false;
    }

    $validFrom = $validFrom ? (int) $validFrom : null;
    $validTo = $validTo ? (int) $validTo : null;

    $now = time();

    if ($validFrom !== null && $now < $validFrom) {
        $status = 'not_yet_valid';
    } elseif ($validTo !== null && $now > $validTo) {
        $status = 'expired';
    } elseif ($validFrom !== null && $validTo !== null) {
        $status = 'valid';
    } else {
        $status = 'verified_details_unavailable';
    }

    $subject = is_array($parsed)
        ? ($parsed['subject']['CN'] ?? null)
        : null;

    $issuer = is_array($parsed)
        ? (
            $parsed['issuer']['CN']
            ?? $parsed['issuer']['O']
            ?? null
        )
        : null;

    return [
        'verification' => 'passed',
        'status' => $status,

        'subject' => $subject
            ?? $leaf['Subject']
            ?? null,

        'issuer' => $issuer
            ?? $leaf['Issuer']
            ?? null,

        'valid_from' => $validFrom
            ? gmdate(DATE_ATOM, $validFrom)
            : null,

        'valid_to' => $validTo
            ? gmdate(DATE_ATOM, $validTo)
            : null,

        'days_remaining' => $validTo !== null
            ? (int) ceil(($validTo - $now) / 86400)
            : null,

        'subject_alt_names' => is_array($parsed)
            ? ($parsed['extensions']['subjectAltName'] ?? null)
            : null,
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
 * Menentukan encoding HTML dan mengonversinya menjadi UTF-8.
 *
 * Prioritas:
 * 1. Byte Order Mark (BOM).
 * 2. Charset dari HTTP Content-Type.
 * 3. Deklarasi charset pada elemen meta HTML.
 * 4. UTF-8 jika valid, selain itu Windows-1252.
 */
private function normalizeHtmlEncoding(
    string $html,
    string $contentType = ''
): string {
    $encoding = null;

    // 1. Periksa BOM.
    if (str_starts_with($html, "\xEF\xBB\xBF")) {
        $encoding = 'UTF-8';
        $html = substr($html, 3);
    } elseif (str_starts_with($html, "\xFF\xFE")) {
        $encoding = 'UTF-16LE';
        $html = substr($html, 2);
    } elseif (str_starts_with($html, "\xFE\xFF")) {
        $encoding = 'UTF-16BE';
        $html = substr($html, 2);
    }

    // 2. Periksa charset dari HTTP Content-Type.
    if (
        $encoding === null
        && preg_match(
            '/\bcharset\s*=\s*["\']?\s*([a-zA-Z0-9._-]+)/i',
            $contentType,
            $matches
        )
    ) {
        $encoding = $matches[1];
    }

    // 3. Periksa deklarasi charset dalam HTML.
    if ($encoding === null) {
        $headerSample = substr($html, 0, 4096);

        if (
            preg_match_all(
                '/<meta\b[^>]*>/i',
                $headerSample,
                $metaTags
            )
        ) {
            foreach ($metaTags[0] as $metaTag) {
                if (
                    preg_match(
                        '/\bcharset\s*=\s*["\']?\s*([a-zA-Z0-9._-]+)/i',
                        $metaTag,
                        $matches
                    )
                ) {
                    $encoding = $matches[1];
                    break;
                }

                if (
                    preg_match(
                        '/\bcontent\s*=\s*["\'][^"\']*charset\s*=\s*([a-zA-Z0-9._-]+)/i',
                        $metaTag,
                        $matches
                    )
                ) {
                    $encoding = $matches[1];
                    break;
                }
            }
        }
    }

    // 4. Tentukan fallback.
    if ($encoding === null) {
        $encoding = preg_match('//u', $html)
            ? 'UTF-8'
            : 'Windows-1252';
    }

    $normalizedEncoding = strtoupper(
        str_replace('_', '-', trim($encoding))
    );

    // Normalisasi beberapa nama encoding umum.
    $aliases = [
        'UTF8' => 'UTF-8',
        'SHIFT-JIS' => 'SJIS-win',
        'SHIFT-JISX0213' => 'SJIS-win',
        'WINDOWS-31J' => 'SJIS-win',
        'CP932' => 'SJIS-win',
        'SJIS' => 'SJIS-win',
        'ISO-8859-1' => 'Windows-1252',
        'LATIN1' => 'Windows-1252',
        'US-ASCII' => 'Windows-1252',
    ];

    $sourceEncoding = $aliases[$normalizedEncoding]
        ?? $normalizedEncoding;

    // Tidak perlu melakukan konversi apabila sudah UTF-8.
    if ($sourceEncoding === 'UTF-8') {
        return $html;
    }

    // Utamakan mbstring.
    if (function_exists('mb_convert_encoding')) {
        try {
            return mb_convert_encoding(
                $html,
                'UTF-8',
                $sourceEncoding
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    // Gunakan iconv sebagai alternatif.
    if (function_exists('iconv')) {
        try {
            $converted = @iconv(
                $sourceEncoding,
                'UTF-8//IGNORE',
                $html
            );

            if ($converted !== false) {
                return $converted;
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    // Jangan menggagalkan keseluruhan proses scan.
    return $html;
}

    /**
 * Mengambil elemen penting dari HTML.
 *
 * Semua teks dinormalisasi menjadi UTF-8 sebelum diproses
 * oleh DOMDocument.
 *
 * LIBXML_NONET mencegah parser melakukan akses jaringan.
 */
private function parseHtml(
    string $html,
    string $contentType = ''
): array {
    $title = null;
    $meta = [];
    $scripts = [];

    $html = $this->normalizeHtmlEncoding(
        $html,
        $contentType
    );

    $previousLibxmlSetting = libxml_use_internal_errors(true);

    try {
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Deklarasi ini membantu libxml membaca dokumen sebagai UTF-8.
        $loaded = @$dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if ($loaded) {
            $titleNodes = $dom->getElementsByTagName('title');

            if ($titleNodes->length > 0) {
                $rawTitle = trim(
                    $titleNodes->item(0)->textContent
                );

                $title = function_exists('mb_substr')
                    ? mb_substr($rawTitle, 0, 500, 'UTF-8')
                    : substr($rawTitle, 0, 500);
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
                    'content' => function_exists('mb_substr')
                        ? mb_substr($content, 0, 1000, 'UTF-8')
                        : substr($content, 0, 1000),
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
 * Mengambil response headers yang berkaitan dengan keamanan browser.
 */
private function extractSecurityHeaders(array $headers): array
{
    $allowed = [
        'content-security-policy',
        'content-security-policy-report-only',
        'strict-transport-security',
        'x-content-type-options',
        'x-frame-options',
        'referrer-policy',
        'permissions-policy',
        'cross-origin-opener-policy',
        'cross-origin-embedder-policy',
        'cross-origin-resource-policy',
    ];

    $result = [];

    foreach ($allowed as $name) {
        if (!isset($headers[$name])) {
            continue;
        }

        $result[$name] = array_values(
            array_map(
                fn ($value) => substr((string) $value, 0, 4096),
                $headers[$name]
            )
        );
    }

    return $result;
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
