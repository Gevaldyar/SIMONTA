
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_technologies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scan_id')
                ->constrained('scans')
                ->cascadeOnDelete();

            $table->foreignId('technology_id')
                ->constrained('technologies')
                ->restrictOnDelete();

            $table->foreignId('signature_id')
                ->nullable()
                ->constrained('technology_signatures')
                ->nullOnDelete();

            // Versi yang ditemukan pada pemeriksaan tersebut.
            $table->string('version', 100)->nullable();

            // Tingkat keyakinan hasil identifikasi, 0 sampai 100.
            $table->unsignedTinyInteger('confidence')->default(0);

            // Bukti deteksi yang sudah disaring.
            $table->json('evidence')->nullable();

            $table->timestamps();

            $table->index(['scan_id', 'technology_id']);
            $table->index('signature_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_technologies');
    }
};
