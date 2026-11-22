
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificate_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('hostname', 253);

            // valid, expiring_soon, expired,
            // verification_failed, unavailable, error
            $table->string('status', 32)->index();

            // passed, failed, unavailable
            $table->string('verification', 32)->nullable();

            $table->text('subject')->nullable();

            $table->text('issuer')->nullable();

            $table->timestamp('valid_from')->nullable();

            $table->timestamp('valid_to')->nullable();

            $table->integer('days_remaining')->nullable();

            $table->json('subject_alt_names')->nullable();

            $table->string('fingerprint_sha256', 64)->nullable();

            $table->timestamp('checked_at');

            $table->text('error_message')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'website_id',
                'checked_at',
            ]);

            $table->index([
                'hostname',
                'checked_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificate_checks');
    }
};
