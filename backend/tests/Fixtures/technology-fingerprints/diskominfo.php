<?php

$scripts = [
    '/_next/static/chunks/331947fbc002e04f.js',
    '/_next/static/chunks/aee6c7720838f8a2.js',
    '/_next/static/chunks/8b1343baaf1368c2.js',
    '/_next/static/chunks/82abf2d65f5428ae.js',
    '/_next/static/chunks/turbopack-42a2e6305b36f243.js',
    '/_next/static/chunks/d3c3cf83a4d75249.js',
    '/_next/static/chunks/15c8ac08f4a21f89.js',
    '/_next/static/chunks/fe64bde66574e17d.js',
    '/_next/static/chunks/33eaef5c9bf2ce7c.js',
    '/_next/static/chunks/ff1a16fafef87110.js',
    '/_next/static/chunks/d3065fe7a2bf848f.js',
    '/_next/static/chunks/6c7d89fc9f1eb81e.js',
    '/_next/static/chunks/c699beb6c9876c87.js',
    '/_next/static/chunks/4285f006c0272fd8.js',
    '/_next/static/chunks/a0db20518cda8350.js',
    '/_next/static/chunks/a6dad97d9634a72d.js',
    '/_next/static/chunks/f1b4d49aa9e249c5.js',
];

$links = [
    ['rel' => 'preload', 'path' => '/_next/static/media/GeistMono_Variable.p.73882635.woff2'],
    ['rel' => 'preload', 'path' => '/_next/static/media/Geist_Variable-s.p.f19e4721.woff2'],
    ['rel' => 'preload', 'path' => '/asset/logo-left.png'],
    ['rel' => 'preload', 'path' => '/asset/small-logo.png'],
    ['rel' => 'preload', 'path' => '/images/tasikhub/dashboard-layanan.jpg'],
    ['rel' => 'stylesheet', 'path' => '/_next/static/chunks/b0241f0722045bbe.css'],
    ['rel' => 'stylesheet', 'path' => '/_next/static/chunks/4bdbed1f333ab131.css'],
    ['rel' => 'preload', 'path' => '/_next/static/chunks/f1b4d49aa9e249c5.js'],
    ['rel' => 'shortcut icon', 'path' => '/asset/small-logo.png'],
    ['rel' => 'icon', 'path' => '/asset/small-logo.png'],
    ['rel' => 'apple-touch-icon', 'path' => '/asset/small-logo.png'],
];

$assetTags = [];

foreach ($scripts as $path) {
    $assetTags[] = '<script src="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"></script>';
}

foreach ($links as $link) {
    $assetTags[] = '<link rel="' . htmlspecialchars($link['rel'], ENT_QUOTES, 'UTF-8')
        . '" href="' . htmlspecialchars($link['path'], ENT_QUOTES, 'UTF-8') . '">';
}

return [
    'name' => 'Diskominfo Kota Tasikmalaya',
    'url' => 'https://diskominfo.tasikmalayakota.go.id/',
    'http_status' => 200,
    'page_title' => 'Diskominfo Kota Tasikmalaya',

    'expected' => [
        'nextjs',
    ],

    'fingerprint' => [
        'url' => 'https://diskominfo.tasikmalayakota.go.id/',
        'final_url' => 'https://diskominfo.tasikmalayakota.go.id/',

        'headers' => [
            'server' => 'aproxier',
            'content-type' => 'text/html; charset=utf-8',
        ],

        'html' => implode("\n", $assetTags),
        'scripts' => $scripts,
        'meta' => [],
        'cookies' => [],
    ],
];