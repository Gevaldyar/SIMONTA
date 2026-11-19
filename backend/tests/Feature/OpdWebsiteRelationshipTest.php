<?php

namespace Tests\Feature;

use App\Models\Opd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpdWebsiteRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_opd_can_have_multiple_websites(): void
    {
        $opd = Opd::create([
            'name' => 'Dinas Contoh',
        ]);

        $website1 = $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        $website2 = $opd->websites()->create([
            'name' => 'Portal Layanan',
            'url' => 'https://service.example.com',
        ]);

        // Satu OPD memiliki dua website.
        $this->assertCount(2, $opd->websites);

        // Kedua website harus terhubung dengan OPD yang benar.
        $this->assertSame($opd->id, $website1->opd->id);
        $this->assertSame($opd->id, $website2->opd->id);

        // Memastikan nilai default tersedia pada objek model.
        $this->assertSame(
            10,
            $website1->monitoring_interval_minutes
        );

        // Memastikan nilai 10 benar-benar tersimpan di database.
        $this->assertSame(
            10,
            $website1->fresh()->monitoring_interval_minutes
        );
    }
}
