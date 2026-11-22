<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class DomainRdapService
{
    private const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';

    private const CACHE_KEY = 'simonta:rdap:dns-bootstrap:v1';

    private const EXPIRING_SOON_DAYS = 30;

    /**
     * Memeriksa informasi registrasi domain menggunakan RDAP.
     *
     * URL website digunakan hanya untuk mengambil hostname.
     * Lookup dilakukan ke RDAP server resmi yang ditemukan
     * melalui IANA bootstrap.
     */
    public function inspect(string $websiteUrl): array
    {
        $checkedAt = now();

        $hostname = $this->extractHostname($websiteUrl);

        if ($hostname === null) {
            return $this->result(
                domain: null,
                status: 'unsupported',
                checkedAt: $checkedAt,
                errorMessage: 'URL website tidak memiliki hostname domain yang valid.'
            );
        }

        try {
            $bootstrap = $this->getBootstrap();

            $rdapServers = $this->resolveRdapServers(
                $hostname,
                $bootstrap
            );

            if ($rdapServers === []) {
                return $this->result(
                    domain: $hostname,
                    status: 'unsupported',
                    checkedAt: $checkedAt,
                    errorMessage: 'Tidak ditemukan RDAP server HTTPS untuk ekstensi domain ini.'
                );
            }

            /*
             * Hostname bisa berupa subdomain.
             * Periksa hostname terlebih dahulu, kemudian parent domain
             * secara bertahap sampai ditemukan objek RDAP.
             *
             * Contoh:
             * portal.example.go.id
             * example.go.id
             * go.id
             */
            foreach ($this->domainCandidates($hostname) as $candidate) {
                $lookup = $this->lookupDomain(
                    $candidate,
                    $rdapServers
                );

                if ($lookup['status'] === 'not_found') {
                    continue;
                }

                if ($lookup['status'] !== 'found') {
                    return $this->result(
                        domain: $candidate,
                        status: 'error',
                        checkedAt: $checkedAt,
                        rdapServer: $lookup['rdap_server'] ?? null,
                        errorMessage: $lookup['error_message']
                            ?? 'RDAP service tidak dapat memberikan hasil.'
                    );
                }

                return $this->parseDomainResponse(
                    $candidate,
                    $lookup['data'],
                    $lookup['rdap_server'],
                    $checkedAt
                );
            }

            return $this->result(
                domain: $hostname,
                status: 'not_found',
                checkedAt: $checkedAt,
                rdapServer: $rdapServers[0],
                errorMessage: 'Informasi registrasi domain tidak ditemukan pada RDAP server.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result(
                domain: $hostname,
                status: 'error',
                checkedAt: $checkedAt,
                errorMessage: 'Pemeriksaan RDAP gagal. Periksa koneksi atau ketersediaan RDAP server.'
            );
        }
    }

    /**
     * Mengambil RDAP DNS bootstrap dari IANA.
     * Hasil disimpan dalam cache selama 24 jam.
     */
    private function getBootstrap(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addDay(),
            function (): array {
                $response = Http::acceptJson()
                    ->connectTimeout(5)
                    ->timeout(12)
                    ->withOptions([
                        'allow_redirects' => false,
                    ])
                    ->get(self::BOOTSTRAP_URL);

                if (! $response->successful()) {
                    throw new \RuntimeException(
                        'IANA RDAP bootstrap request failed.'
                    );
                }

                $data = $response->json();

                if (
                    ! is_array($data)
                    || ! isset($data['services'])
                    || ! is_array($data['services'])
                ) {
                    throw new \RuntimeException(
                        'Invalid IANA RDAP bootstrap response.'
                    );
                }

                return $data;
            }
        );
    }

    /**
     * Menemukan RDAP server dengan label-wise longest match.
     *
     * Hanya endpoint HTTPS yang digunakan.
     */
    private function resolveRdapServers(
        string $hostname,
        array $bootstrap
    ): array {
        $bestMatchLength = -1;
        $selectedServers = [];

        foreach ($bootstrap['services'] ?? [] as $service) {
            if (
                ! is_array($service)
                || ! isset($service[0], $service[1])
                || ! is_array($service[0])
                || ! is_array($service[1])
            ) {
                continue;
            }

            $suffixes = $service[0];
            $servers = $service[1];

            foreach ($suffixes as $suffix) {
                if (! is_string($suffix)) {
                    continue;
                }

                $suffix = strtolower(trim($suffix, '.'));

                $matches = $suffix === ''
                    || $hostname === $suffix
                    || str_ends_with($hostname, '.' . $suffix);

                if (! $matches) {
                    continue;
                }

                $matchLength = $suffix === ''
                    ? 0
                    : substr_count($suffix, '.') + 1;

                if ($matchLength < $bestMatchLength) {
                    continue;
                }

                $httpsServers = [];

                foreach ($servers as $server) {
                    if (! is_string($server)) {
                        continue;
                    }

                    $server = rtrim(trim($server), '/');

                    $parts = parse_url($server);

                    if (
                        ! is_array($parts)
                        || strtolower($parts['scheme'] ?? '') !== 'https'
                        || empty($parts['host'])
                        || isset($parts['user'])
                        || isset($parts['pass'])
                        || isset($parts['query'])
                        || isset($parts['fragment'])
                    ) {
                        continue;
                    }

                    $httpsServers[] = $server;
                }

                if ($matchLength > $bestMatchLength) {
                    $bestMatchLength = $matchLength;
                    $selectedServers = $httpsServers;
                } elseif ($matchLength === $bestMatchLength) {
                    $selectedServers = array_merge(
                        $selectedServers,
                        $httpsServers
                    );
                }
            }
        }

        return array_values(array_unique($selectedServers));
    }

    /**
     * Mencoba hostname dan parent domain secara bertahap.
     */
    private function domainCandidates(string $hostname): array
    {
        $labels = explode('.', $hostname);
        $candidates = [];

        while (count($labels) >= 2) {
            $candidates[] = implode('.', $labels);
            array_shift($labels);
        }

        return $candidates;
    }

    /**
     * Meminta objek domain ke RDAP server.
     *
     * Redirect sengaja tidak diikuti secara otomatis.
     */
    private function lookupDomain(
        string $domain,
        array $rdapServers
    ): array {
        $lastServer = null;

        foreach (array_slice($rdapServers, 0, 3) as $server) {
            $lastServer = $server;

            $url = $server . '/domain/' . rawurlencode($domain);

            $response = Http::accept(
                'application/rdap+json, application/json'
            )
                ->connectTimeout(5)
                ->timeout(12)
                ->withOptions([
                    'allow_redirects' => false,
                ])
                ->get($url);

            if ($response->status() === 404) {
                continue;
            }

            if ($response->status() >= 500 || $response->status() === 429) {
                return [
                    'status' => 'error',
                    'rdap_server' => $server,
                    'error_message' => 'RDAP server sedang tidak tersedia atau membatasi permintaan.',
                ];
            }

            if (! $response->successful()) {
                return [
                    'status' => 'error',
                    'rdap_server' => $server,
                    'error_message' => 'RDAP server memberikan respons HTTP '
                        . $response->status() . '.',
                ];
            }

            $data = $response->json();

            if (
                ! is_array($data)
                || ($data['objectClassName'] ?? null) !== 'domain'
            ) {
                return [
                    'status' => 'error',
                    'rdap_server' => $server,
                    'error_message' => 'Respons RDAP tidak berisi objek domain yang valid.',
                ];
            }

            return [
                'status' => 'found',
                'rdap_server' => $server,
                'data' => $data,
            ];
        }

        return [
            'status' => 'not_found',
            'rdap_server' => $lastServer,
        ];
    }

    /**
     * Mengubah objek RDAP menjadi struktur data SIMONTA.
     */
    private function parseDomainResponse(
        string $fallbackDomain,
        array $data,
        string $rdapServer,
        Carbon $checkedAt
    ): array {
        $domain = strtolower(
            $data['ldhName'] ?? $fallbackDomain
        );

        $events = $data['events'] ?? [];

        $registeredAt = $this->eventDate(
            $events,
            ['registration', 'creation']
        );

        $expiresAt = $this->eventDate(
            $events,
            ['expiration']
        );

        $registrar = $this->extractRegistrar(
            $data['entities'] ?? []
        );

        $nameservers = collect($data['nameservers'] ?? [])
            ->pluck('ldhName')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn ($value) => strtolower($value))
            ->unique()
            ->values()
            ->all();

        $daysRemaining = null;

        if ($expiresAt !== null) {
            $daysRemaining = (int) floor(
                ($expiresAt->getTimestamp() - $checkedAt->getTimestamp())
                / 86400
            );

            if ($expiresAt->lessThanOrEqualTo($checkedAt)) {
                $status = 'expired';
            } elseif ($daysRemaining <= self::EXPIRING_SOON_DAYS) {
                $status = 'expiring_soon';
            } else {
                $status = 'valid';
            }
        } else {
            $status = 'unavailable';
        }

        return [
            'domain' => $domain,
            'status' => $status,
            'registrar' => $registrar,
            'registered_at' => $registeredAt?->toISOString(),
            'expires_at' => $expiresAt?->toISOString(),
            'days_remaining' => $daysRemaining,
            'nameservers' => $nameservers,
            'rdap_server' => $rdapServer,
            'checked_at' => $checkedAt->toISOString(),
            'error_message' => $expiresAt === null
                ? 'RDAP tidak menyediakan tanggal kedaluwarsa domain.'
                : null,
            'metadata' => [
                'rdap_handle' => $data['handle'] ?? null,
                'rdap_status' => $data['status'] ?? [],
            ],
        ];
    }

    /**
     * Mengambil tanggal berdasarkan eventAction RDAP.
     */
    private function eventDate(
        array $events,
        array $acceptedActions
    ): ?Carbon {
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $action = strtolower(
                trim($event['eventAction'] ?? '')
            );

            if (
                ! in_array($action, $acceptedActions, true)
                || empty($event['eventDate'])
            ) {
                continue;
            }

            try {
                return Carbon::parse($event['eventDate']);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Mengambil nama registrar dari vCard RDAP.
     */
    private function extractRegistrar(array $entities): ?string
    {
        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $roles = $entity['roles'] ?? [];

            if (! in_array('registrar', $roles, true)) {
                continue;
            }

            $vcard = $entity['vcardArray'][1] ?? [];

            foreach ($vcard as $entry) {
                if (! is_array($entry) || count($entry) < 4) {
                    continue;
                }

                $field = strtolower($entry[0] ?? '');

                if (! in_array($field, ['fn', 'org'], true)) {
                    continue;
                }

                $value = $entry[3];

                if (is_array($value)) {
                    $value = implode(' ', $value);
                }

                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }

    /**
     * Memvalidasi dan mengambil hostname dari URL website.
     */
    private function extractHostname(string $websiteUrl): ?string
    {
        $parts = parse_url(trim($websiteUrl));

        if (
            ! is_array($parts)
            || ! in_array(
                strtolower($parts['scheme'] ?? ''),
                ['http', 'https'],
                true
            )
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $hostname = strtolower(
            rtrim($parts['host'], '.')
        );

        // Konversi hostname IDN menjadi ASCII/Punycode.
        if (preg_match('/[^\x20-\x7E]/', $hostname)) {
            if (! function_exists('idn_to_ascii')) {
                return null;
            }

            $hostname = idn_to_ascii(
                $hostname,
                IDNA_DEFAULT,
                INTL_IDNA_VARIANT_UTS46
            );

            if (! is_string($hostname)) {
                return null;
            }

            $hostname = strtolower($hostname);
        }

        if (
            strlen($hostname) > 253
            || str_contains($hostname, '..')
            || filter_var($hostname, FILTER_VALIDATE_IP)
            || ! str_contains($hostname, '.')
            || ! filter_var(
                $hostname,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            )
        ) {
            return null;
        }

        return $hostname;
    }

    /**
     * Struktur respons untuk kondisi yang tidak memiliki data lengkap.
     */
    private function result(
        ?string $domain,
        string $status,
        Carbon $checkedAt,
        ?string $rdapServer = null,
        ?string $errorMessage = null
    ): array {
        return [
            'domain' => $domain,
            'status' => $status,
            'registrar' => null,
            'registered_at' => null,
            'expires_at' => null,
            'days_remaining' => null,
            'nameservers' => [],
            'rdap_server' => $rdapServer,
            'checked_at' => $checkedAt->toISOString(),
            'error_message' => $errorMessage,
            'metadata' => [],
        ];
    }
}
