<?php

$scripts = [
    '/build/assets/app-23eee586.js',
];

$links = [
    ['rel' => 'shortcut icon', 'path' => '/img/logo_tasik.svg'],
    ['rel' => 'preload', 'path' => '/build/assets/app-6de2a29f.css'],
    ['rel' => 'modulepreload', 'path' => '/build/assets/app-23eee586.js'],
    ['rel' => 'stylesheet', 'path' => '/build/assets/app-6de2a29f.css'],
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
    'name' => 'Dinas Kesehatan Kota Tasikmalaya',
    'url' => 'https://dinkes.tasikmalayakota.go.id/',
    'http_status' => 200,
    'page_title' => 'DINKES Kota Tasikmalaya',

    'expected' => [
        'apache',
        'laravel',
    ],

    'fingerprint' => [
        'url' => 'https://dinkes.tasikmalayakota.go.id/',
        'final_url' => 'https://dinkes.tasikmalayakota.go.id/',

        'headers' => [
            'server' => 'Apache',
            'content-type' => 'text/html; charset=UTF-8',
        ],

        'html' => implode("\n", $assetTags),
        'scripts' => $scripts,
        'meta' => [],

        // Hanya nama cookie yang disimpan.
        // Tidak menyimpan session atau token asli.
        'cookies' => [
            'XSRF-TOKEN' => '',
            'laravel_session' => '',
        ],
    ],
];