<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->restrictOnDelete();

            // queued, running, completed, failed.
            $table->string('status', 30)->default('queued');

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Hasil komunikasi HTTP.
            $table->unsignedSmallInteger('http_status_code')->nullable();

            // Waktu respons dalam milidetik.
            $table->unsignedInteger('response_time_ms')->nullable();

            $table->string('final_url', 2048)->nullable();
            $table->string('page_title')->nullable();

            $table->text('error_message')->nullable();

            // Informasi teknis tambahan yang sudah disaring.
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['website_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
