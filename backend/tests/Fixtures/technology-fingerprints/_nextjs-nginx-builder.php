<?php

return static function (
    string $name,
    string $url,
    string $title,
    string $logoPath
): array {
    // Asset JavaScript yang ditemukan pada ketiga website.
    $scripts = [
        '/_next/static/chunks/0ma1qupzl8bb3.js',
        '/_next/static/chunks/0xqatr-x7.doo.js',
        '/_next/static/chunks/0qeia7isnkz.n.js',
        '/_next/static/chunks/07uz2g0_38qia.js',
        '/_next/static/chunks/turbopack-0~-020j8n9b.a.js',
        '/_next/static/chunks/0--s82mlhpiiv.js',
        '/_next/static/chunks/16x8dcbubosvb.js',
        '/_next/static/chunks/0iyglkdr-7m4w.js',
        '/_next/static/chunks/0qpcmkgzpiua-.js',
        '/_next/static/chunks/01o0gnnj_krtn.js',
        '/_next/static/chunks/0l4dus97l6cgy.js',
        '/_next/static/chunks/09ji-i15sicr5.js',
        '/_next/static/chunks/112dgsx46jipz.js',
        '/_next/static/chunks/11nmvxv6j8nad.js',
        '/_next/static/chunks/0svefliea-yan.js',
        '/_next/static/chunks/03f~n4irtrmrn.js',
        '/_next/static/chunks/0svje1frl5c-..js',
        '/_next/static/chunks/0997qm9xx58n4.js',
        '/_next/static/chunks/0dd036w3-8to2.js',
        '/_next/static/chunks/0pwj72u0se1vu.js',
        '/_next/static/chunks/0wb2hb9.gv0og.js',
        '/_next/static/chunks/03~yq9q893hmn.js',
        '/_next/static/chunks/0fldi-4fgo6om.js',
    ];

    // Asset stylesheet dan link yang ditemukan pada ketiga website.
    $links = [
        [
            'rel' => 'stylesheet',
            'path' => '/_next/static/chunks/14~mjwwfpcgkb.css',
        ],
        [
            'rel' => 'stylesheet',
            'path' => '/_next/static/chunks/0mr1-56fdiocb.css',
        ],
        [
            'rel' => 'preload',
            'path' => '/_next/static/chunks/0fldi-4fgo6om.js',
        ],
        [
            'rel' => 'icon',
            'path' => $logoPath,
        ],
        [
            'rel' => 'apple-touch-icon',
            'path' => $logoPath,
        ],
    ];

    // Membentuk HTML asset untuk mesin deteksi.
    $assetTags = [];

    foreach ($scripts as $path) {
        $safePath = htmlspecialchars(
            $path,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $assetTags[] = '<script src="' . $safePath . '"></script>';
    }

    foreach ($links as $link) {
        $safeRel = htmlspecialchars(
            $link['rel'],
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $safePath = htmlspecialchars(
            $link['path'],
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $assetTags[] = '<link rel="' . $safeRel
            . '" href="' . $safePath . '">';
    }

    return [
        'name' => $name,
        'url' => $url,
        'http_status' => 200,
        'page_title' => $title,
        'captured_at' => '2026-11-18',

        // Kedua teknologi ini memiliki bukti fingerprint.
        'expected' => [
            'nextjs',
            'nginx',
        ],

        'fingerprint' => [
            'url' => $url,
            'final_url' => $url,

            'headers' => [
                'server' => 'nginx',
                'content-type' => 'text/html; charset=utf-8',
            ],

            'html' => implode("\n", $assetTags),
            'scripts' => $scripts,
            'meta' => [],
            'cookies' => [],
        ],
    ];
};