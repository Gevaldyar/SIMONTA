<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opd_id')
                ->constrained('opds')
                ->restrictOnDelete();

            $table->string('name')->nullable();
            $table->string('url', 2048);

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('monitoring_enabled')->default(true);

            $table->unsignedSmallInteger('monitoring_interval_minutes')
                ->default(60);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'is_active']);
            $table->index(['monitoring_enabled', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
