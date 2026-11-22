
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_registration_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('domain', 253);

            // valid, expiring_soon, expired,
            // not_found, unavailable, unsupported, error
            $table->string('status', 32)->index();

            $table->string('registrar')->nullable();

            $table->timestamp('registered_at')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->integer('days_remaining')->nullable();

            $table->json('nameservers')->nullable();

            $table->string('rdap_server', 2048)->nullable();

            $table->timestamp('checked_at');

            $table->text('error_message')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'website_id',
                'checked_at',
            ]);

            $table->index([
                'domain',
                'checked_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_registration_checks');
    }
};
