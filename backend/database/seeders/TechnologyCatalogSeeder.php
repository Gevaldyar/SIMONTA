<?php

namespace Database\Seeders;

use App\Models\Technology;
use Illuminate\Database\Seeder;

class TechnologyCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            // =====================================================
            // BACKEND LANGUAGES
            // =====================================================
            [
                'technology' => [
                    'name' => 'PHP',
                    'slug' => 'php',
                    'category' => 'backend_language',
                    'vendor' => 'PHP Group',
                    'description' => 'Server-side programming language.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'PHP X-Powered-By Header',
                        'type' => 'header',
                        'target' => 'x-powered-by',
                        'pattern' => 'PHP/',
                        'match_type' => 'contains',
                        'version_pattern' => '/PHP\/([0-9]+(?:\.[0-9]+){1,2})/i',
                        'confidence' => 95,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'PHP Session Cookie',
                        'type' => 'cookie',
                        'target' => null,
                        'pattern' => 'PHPSESSID',
                        'match_type' => 'contains',
                        'version_pattern' => null,
                        'confidence' => 75,
                        'is_active' => true,
                    ],
                ],
            ],

            // =====================================================
            // BACKEND FRAMEWORKS
            // =====================================================
            [
                'technology' => [
                    'name' => 'Laravel',
                    'slug' => 'laravel',
                    'category' => 'backend_framework',
                    'vendor' => 'Laravel',
                    'description' => 'PHP web application framework.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Laravel Session Cookie',
                        'type' => 'cookie',
                        'target' => null,
                        'pattern' => 'laravel_session',
                        'match_type' => 'contains',
                        'version_pattern' => null,
                        'confidence' => 90,
                        'is_active' => true,
                    ],
                ],
            ],

            // =====================================================
            // CMS
            // =====================================================
            [
                'technology' => [
                    'name' => 'WordPress',
                    'slug' => 'wordpress',
                    'category' => 'cms',
                    'vendor' => 'WordPress Foundation',
                    'description' => 'Content management system.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'WordPress Generator Metadata',
                        'type' => 'meta',
                        'target' => 'generator',
                        'pattern' => 'WordPress',
                        'match_type' => 'contains',
                        'version_pattern' => '/WordPress\s+([0-9]+(?:\.[0-9]+){1,2})/i',
                        'confidence' => 100,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'WordPress Content Path',
                        'type' => 'script',
                        'target' => null,
                        'pattern' => '/wp-content/',
                        'match_type' => 'contains',
                        'version_pattern' => null,
                        'confidence' => 90,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'technology' => [
                    'name' => 'Drupal',
                    'slug' => 'drupal',
                    'category' => 'cms',
                    'vendor' => 'Drupal Association',
                    'description' => 'Open-source content management system.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Drupal Generator Metadata',
                        'type' => 'meta',
                        'target' => 'generator',
                        'pattern' => 'Drupal',
                        'match_type' => 'contains',
                        'version_pattern' => '/Drupal\s+([0-9]+(?:\.[0-9]+){1,2})/i',
                        'confidence' => 100,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Drupal Default Files Path',
                        'type' => 'html',
                        'target' => null,
                        'pattern' => '/sites/default/files/',
                        'match_type' => 'contains',
                        'version_pattern' => null,
                        'confidence' => 80,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'technology' => [
                    'name' => 'Joomla',
                    'slug' => 'joomla',
                    'category' => 'cms',
                    'vendor' => 'Open Source Matters',
                    'description' => 'Open-source content management system.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Joomla Generator Metadata',
                        'type' => 'meta',
                        'target' => 'generator',
                        'pattern' => 'Joomla',
                        'match_type' => 'contains',
                        'version_pattern' => '/Joomla!?\s*([0-9]+(?:\.[0-9]+){1,2})/i',
                        'confidence' => 100,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Joomla Component URL',
                        'type' => 'html',
                        'target' => null,
                        'pattern' => 'option=com_',
                        'match_type' => 'contains',
                        'version_pattern' => null,
                        'confidence' => 80,
                        'is_active' => true,
                    ],
                ],
            ],

            // =====================================================
            // FRONTEND FRAMEWORKS
            // =====================================================
            [
                'technology' => [
                    'name' => 'React',
                    'slug' => 'react',
                    'category' => 'frontend_framework',
                    'vendor' => 'Meta',
                    'description' => 'JavaScript library for building user interfaces.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'React HTML Markers',
                        'type' => 'html',
                        'target' => null,
                        'pattern' => '/(?:data-reactroot|data-reactid|__reactContainer)/i',
                        'match_type' => 'regex',
                        'version_pattern' => null,
                        'confidence' => 80,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'React Script Filename',
                        'type' => 'script',
                        'target' => null,
                        'pattern' => '/react(?:-dom)?(?:\.production)?(?:\.min)?\.js/i',
                        'match_type' => 'regex',
                        'version_pattern' => null,
                        'confidence' => 85,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'technology' => [
                    'name' => 'Vue.js',
                    'slug' => 'vuejs',
                    'category' => 'frontend_framework',
                    'vendor' => 'Vue.js',
                    'description' => 'Progressive JavaScript framework.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Vue Scoped HTML Attribute',
                        'type' => 'html',
                        'target' => null,
                        'pattern' => '/\bdata-v-[a-z0-9]{4,}\b/i',
                        'match_type' => 'regex',
                        'version_pattern' => null,
                        'confidence' => 75,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Vue Script Filename',
                        'type' => 'script',
                        'target' => null,
                        'pattern' => '/vue(?:\.runtime)?(?:\.global)?(?:\.prod)?(?:\.min)?\.js/i',
                        'match_type' => 'regex',
                        'version_pattern' => null,
                        'confidence' => 90,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'technology' => [
                    'name' => 'Angular',
                    'slug' => 'angular',
                    'category' => 'frontend_framework',
                    'vendor' => 'Google',
                    'description' => 'Web application framework.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Angular Version Attribute',
                        'type' => 'html',
                        'target' => null,
                        'pattern' => '/\bng-version\s*=\s*["\'][^"\']+["\']/i',
                        'match_type' => 'regex',
                        'version_pattern' => '/\bng-version\s*=\s*["\']([^"\']+)["\']/i',
                        'confidence' => 95,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'technology' => [
                    'name' => 'Next.js',
                    'slug' => 'nextjs',
                    'category' => 'frontend_framework',
                    'vendor' => 'Vercel',
                    'website_url' => 'https://nextjs.org',
                    'description' => 'React-based framework for building web applications.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Next.js Static Asset Reference',
                        'type' => 'html',
                        'target' => null,
                        'pattern' => '~(?:src|href)\s*=\s*["\'][^"\']*/_next/static/(?:chunks|webpack)/[^"\']*["\']~i',
                        'match_type' => 'regex',
                        'version_pattern' => null,
                        'confidence' => 95,
                        'is_active' => true,
                    ],
                ],
            ],

            // ======================================
// VUE-BASED FRAMEWORKS
// ======================================

[
    'technology' => [
        'name' => 'Nuxt',
        'slug' => 'nuxt',
        'category' => 'frontend_framework',
        'vendor' => 'Nuxt',
        'website_url' => 'https://nuxt.com',
        'description' => 'Vue-based framework for building full-stack web applications.',
        'is_active' => true,
    ],
    'signatures' => [
        [
            'name' => 'Nuxt Static Asset Reference',
            'type' => 'html',
            'target' => null,
            'pattern' => '~<(?:script|link)\b[^>]*\b(?:src|href)\s*=\s*["\'][^"\']*/_nuxt/[^"\']*["\'][^>]*>~i',
            'match_type' => 'regex',
            'version_pattern' => null,
            'confidence' => 95,
            'is_active' => true,
        ],
    ],
],

[
    'technology' => [
        'name' => 'SvelteKit',
        'slug' => 'sveltekit',
        'category' => 'frontend_framework',
        'vendor' => 'Svelte',
        'website_url' => 'https://svelte.dev/docs/kit',
        'description' => 'Application framework built on Svelte.',
        'is_active' => true,
    ],
    'signatures' => [
        [
            'name' => 'SvelteKit Immutable Asset Reference',
            'type' => 'html',
            'target' => null,
            'pattern' => '~<(?:script|link)\b[^>]*\b(?:src|href)\s*=\s*["\'][^"\']*/_app/immutable/[^"\']*["\'][^>]*>~i',
            'match_type' => 'regex',
            'version_pattern' => null,
            'confidence' => 95,
            'is_active' => true,
        ],
    ],
],

[
    'technology' => [
        'name' => 'Astro',
        'slug' => 'astro',
        'category' => 'frontend_framework',
        'vendor' => 'Astro',
        'website_url' => 'https://astro.build',
        'description' => 'Web framework for building content-focused websites.',
        'is_active' => true,
    ],
    'signatures' => [
        [
            'name' => 'Astro Static Asset Reference',
            'type' => 'html',
            'target' => null,
            'pattern' => '~<(?:script|link)\b[^>]*\b(?:src|href)\s*=\s*["\'][^"\']*/_astro/[^"\']*["\'][^>]*>~i',
            'match_type' => 'regex',
            'version_pattern' => null,
            'confidence' => 90,
            'is_active' => true,
        ],
    ],
],

            // =====================================================
            // JAVASCRIPT LIBRARIES
            // =====================================================
            [
                'technology' => [
                    'name' => 'jQuery',
                    'slug' => 'jquery',
                    'category' => 'javascript_library',
                    'vendor' => 'OpenJS Foundation',
                    'description' => 'JavaScript library for DOM manipulation.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'jQuery Script Filename',
                        'type' => 'script',
                        'target' => null,
                        'pattern' => 'jquery',
                        'match_type' => 'contains',
                        'version_pattern' => '/jquery(?:[-.]|\/)([0-9]+(?:\.[0-9]+){1,2})/i',
                        'confidence' => 90,
                        'is_active' => true,
                    ],
                ],
            ],

            // =====================================================
            // CSS FRAMEWORKS
            // =====================================================
            [
                'technology' => [
                    'name' => 'Bootstrap',
                    'slug' => 'bootstrap',
                    'category' => 'css_framework',
                    'vendor' => 'The Bootstrap Team',
                    'description' => 'Frontend CSS framework.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Bootstrap CSS or JS Filename',
                        'type' => 'html',
                        'target' => null,
                        'pattern' => '/bootstrap(?:\.min)?\.(?:css|js)/i',
                        'match_type' => 'regex',
                        'version_pattern' => null,
                        'confidence' => 90,
                        'is_active' => true,
                    ],
                ],
            ],

            // =====================================================
            // WEB SERVERS
            // =====================================================
            [
                'technology' => [
                    'name' => 'Nginx',
                    'slug' => 'nginx',
                    'category' => 'web_server',
                    'vendor' => 'F5',
                    'description' => 'Web server and reverse proxy.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Nginx Server Header',
                        'type' => 'header',
                        'target' => 'server',
                        'pattern' => 'nginx',
                        'match_type' => 'contains',
                        'version_pattern' => '/nginx\/([0-9]+(?:\.[0-9]+){1,2})/i',
                        'confidence' => 95,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'technology' => [
                    'name' => 'Apache',
                    'slug' => 'apache',
                    'category' => 'web_server',
                    'vendor' => 'Apache Software Foundation',
                    'description' => 'Apache HTTP Server.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Apache Server Header',
                        'type' => 'header',
                        'target' => 'server',
                        'pattern' => 'Apache',
                        'match_type' => 'contains',
                        'version_pattern' => '/Apache\/([0-9]+(?:\.[0-9]+){1,2})/i',
                        'confidence' => 95,
                        'is_active' => true,
                    ],
                ],
            ],

            // =====================================================
            // CDN AND PROXY
            // =====================================================
            [
                'technology' => [
                    'name' => 'Cloudflare',
                    'slug' => 'cloudflare',
                    'category' => 'cdn_proxy',
                    'vendor' => 'Cloudflare',
                    'description' => 'Content delivery network and web security service.',
                    'is_active' => true,
                ],
                'signatures' => [
                    [
                        'name' => 'Cloudflare Server Header',
                        'type' => 'header',
                        'target' => 'server',
                        'pattern' => 'cloudflare',
                        'match_type' => 'contains',
                        'version_pattern' => null,
                        'confidence' => 95,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Cloudflare Cache Status Header',
                        'type' => 'header',
                        'target' => 'cf-cache-status',
                        'pattern' => '/^(?:HIT|MISS|DYNAMIC|BYPASS|EXPIRED|REVALIDATED|STALE|UPDATING|NONE)$/i',
                        'match_type' => 'regex',
                        'version_pattern' => null,
                        'confidence' => 85,
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        foreach ($catalog as $entry) {
            // Create or update technology using its unique slug.
            $technology = Technology::updateOrCreate(
                ['slug' => $entry['technology']['slug']],
                $entry['technology']
            );

            foreach ($entry['signatures'] as $signature) {
                $name = $signature['name'];
                unset($signature['name']);

                // Create or update each signature without duplicates.
                $technology->signatures()->updateOrCreate(
                    ['name' => $name],
                    $signature
                );
            }
        }

        $this->command?->info('Technology catalog berhasil disinkronkan.');
        $this->command?->info('Total teknologi: ' . Technology::count());
        $this->command?->info(
            'Total signature: ' . \App\Models\TechnologySignature::count()
        );
    }
}
