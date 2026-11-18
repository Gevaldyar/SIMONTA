<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technology_changes', function (Blueprint $table) {
            $table->id();

            // Scan terbaru yang menghasilkan perubahan.
            $table->foreignId('scan_id')
                ->constrained('scans')
                ->cascadeOnDelete();

            // Scan sebelumnya yang menjadi pembanding.
            $table->foreignId('previous_scan_id')
                ->nullable()
                ->constrained('scans')
                ->nullOnDelete();

            $table->foreignId('technology_id')
                ->constrained('technologies')
                ->restrictOnDelete();

            // Snapshot identitas teknologi saat perubahan tercatat.
            $table->string('technology_name');
            $table->string('technology_slug');

            // added, removed, version_changed.
            $table->string('change_type', 30);

            // Snapshot kondisi sebelumnya.
            $table->string('previous_version', 100)->nullable();
            $table->unsignedTinyInteger('previous_confidence')->nullable();
            $table->json('previous_evidence')->nullable();

            // Snapshot kondisi terbaru.
            $table->string('current_version', 100)->nullable();
            $table->unsignedTinyInteger('current_confidence')->nullable();
            $table->json('current_evidence')->nullable();

            $table->timestamps();

            // Satu teknologi hanya memiliki satu jenis perubahan
            // dalam satu scan.
            $table->unique(
                ['scan_id', 'technology_id'],
                'technology_changes_scan_technology_unique'
            );

            $table->index(['scan_id', 'change_type']);
            $table->index('previous_scan_id');
            $table->index('technology_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technology_changes');
    }
};