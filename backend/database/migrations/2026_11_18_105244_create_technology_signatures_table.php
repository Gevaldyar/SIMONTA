<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technology_signatures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('technology_id')
                ->constrained('technologies')
                ->restrictOnDelete();

            $table->string('name');

            // Contoh: header, html, script, meta, cookie, url.
            $table->string('type', 50);

            // Target yang diperiksa, misalnya x-powered-by.
            $table->string('target')->nullable();

            // Pola yang digunakan untuk mendeteksi teknologi.
            $table->text('pattern');

            // contains, equals, regex, starts_with.
            $table->string('match_type', 30)->default('contains');

            // Pola opsional untuk mengambil nomor versi.
            $table->text('version_pattern')->nullable();

            // Tingkat keyakinan dasar, dari 0 sampai 100.
            $table->unsignedTinyInteger('confidence')->default(80);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['technology_id', 'is_active']);
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technology_signatures');
    }
};
