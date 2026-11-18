<?php

namespace App\Services;

use InvalidArgumentException;

class PublicUrlValidator
{
    /**
     * Memvalidasi URL dan memastikan seluruh alamat IP
     * yang dihasilkan merupakan alamat IP publik.
     *
     * Belum melakukan HTTP request.
     */
    public function validate(string $url): array
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048) {
            throw new InvalidArgumentException(
                'URL kosong atau melebihi batas panjang.'
            );
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            throw new InvalidArgumentException(
                'Format URL tidak valid.'
            );
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'Hanya protokol HTTP dan HTTPS yang diperbolehkan.'
            );
        }

        if (
            isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'URL tidak boleh mengandung username atau password.'
            );
        }

        $host = strtolower(
            trim((string) ($parts['host'] ?? ''), '[]')
        );

        if ($host === '') {
            throw new InvalidArgumentException(
                'Hostname tidak ditemukan.'
            );
        }

        $port = $parts['port'] ?? (
            $scheme === 'https' ? 443 : 80
        );

        $expectedPort = $scheme === 'https' ? 443 : 80;

        if ($port !== $expectedPort) {
            throw new InvalidArgumentException(
                'Port tidak diperbolehkan.'
            );
        }

        // Hapus fragment karena tidak dikirim ke server.
        $normalizedUrl = preg_replace('/#.*$/s', '', $url);

        if (!is_string($normalizedUrl) || $normalizedUrl === '') {
            throw new InvalidArgumentException(
                'URL tidak dapat dinormalisasi.'
            );
        }

        // IP literal tidak perlu melalui resolusi DNS.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            $this->validateHostname($host);

            $addresses = $this->resolveHost($host);
        }

        if (empty($addresses)) {
            throw new InvalidArgumentException(
                'Hostname tidak memiliki alamat IP yang dapat digunakan.'
            );
        }

        $addresses = array_values(array_unique($addresses));

        // Seluruh alamat IP harus publik.
        // Jika satu saja berbahaya, seluruh URL ditolak.
        foreach ($addresses as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new InvalidArgumentException(
                    'URL mengarah ke alamat IP privat atau tidak aman.'
                );
            }
        }

        return [
            'url' => $normalizedUrl,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'addresses' => $addresses,
        ];
    }

    /**
     * Memvalidasi format dan nama domain.
     */
    private function validateHostname(string $host): void
    {
        if (
            str_contains($host, ' ')
            || str_ends_with($host, '.')
            || !str_contains($host, '.')
        ) {
            throw new InvalidArgumentException(
                'Hostname tidak valid.'
            );
        }

        if (
            filter_var(
                $host,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) === false
        ) {
            throw new InvalidArgumentException(
                'Format domain tidak valid.'
            );
        }

        // Domain yang tidak boleh digunakan.
        $blockedSuffixes = [
            'localhost',
            'local',
            'internal',
            'test',
            'example',
            'invalid',
            'home.arpa',
            'onion',
        ];

        foreach ($blockedSuffixes as $suffix) {
            if (
                $host === $suffix
                || str_ends_with($host, '.' . $suffix)
            ) {
                throw new InvalidArgumentException(
                    'Domain lokal atau nonpublik tidak diperbolehkan.'
                );
            }
        }
    }

    /**
     * Mengambil seluruh alamat IPv4 dan IPv6 dari DNS.
     */
    protected function resolveHost(string $host): array
    {
        $records = @dns_get_record(
            $host,
            DNS_A | DNS_AAAA
        );

        if (!is_array($records)) {
            throw new InvalidArgumentException(
                'Tidak dapat melakukan resolusi DNS.'
            );
        }

        $addresses = [];

        foreach ($records as $record) {
            if (
                ($record['type'] ?? '') === 'A'
                && isset($record['ip'])
            ) {
                $addresses[] = $record['ip'];
            }

            if (
                ($record['type'] ?? '') === 'AAAA'
                && isset($record['ipv6'])
            ) {
                $addresses[] = $record['ipv6'];
            }
        }

        if (empty($addresses)) {
            throw new InvalidArgumentException(
                'Domain tidak mempunyai alamat IPv4 atau IPv6.'
            );
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Menentukan apakah IP termasuk alamat publik.
     */
    private function isPublicIp(string $ip): bool
    {
        $flags =
            FILTER_FLAG_NO_PRIV_RANGE
            | FILTER_FLAG_NO_RES_RANGE;

        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                $flags
            ) === false
        ) {
            return false;
        }

        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        // Menolak IPv4-mapped IPv6.
        if (
            strlen($packed) === 16
            && substr($packed, 0, 10) === str_repeat("\0", 10)
            && substr($packed, 10, 2) === "\xff\xff"
        ) {
            return false;
        }

        // Menolak Carrier-Grade NAT: 100.64.0.0/10.
        if (
            strlen($packed) === 4
            && $this->isInCidr($ip, '100.64.0.0/10')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Memeriksa apakah alamat IP berada dalam CIDR tertentu.
     */
    private function isInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr);

        $ipBinary = @inet_pton($ip);
        $networkBinary = @inet_pton($network);

        if (
            $ipBinary === false
            || $networkBinary === false
            || strlen($ipBinary) !== strlen($networkBinary)
        ) {
            return false;
        }

        $prefix = (int) $prefix;

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if (
            substr($ipBinary, 0, $fullBytes)
            !== substr($networkBinary, 0, $fullBytes)
        ) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (
            (ord($ipBinary[$fullBytes]) & $mask)
            ===
            (ord($networkBinary[$fullBytes]) & $mask)
        );
    }
}
