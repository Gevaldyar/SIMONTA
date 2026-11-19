
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Konversi interval lama yang tidak termasuk
        // pilihan baru menjadi 30 menit.
        DB::table('websites')
            ->whereNotIn('monitoring_interval_minutes', [1, 5, 10, 30])
            ->update([
                'monitoring_interval_minutes' => 30,
            ]);

        // Default untuk website baru adalah 10 menit.
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedSmallInteger('monitoring_interval_minutes')
                ->default(10)
                ->change();
        });
    }

    public function down(): void
    {
        // Mengembalikan seluruh interval ke konfigurasi lama.
        DB::table('websites')
            ->whereIn('monitoring_interval_minutes', [1, 5, 10, 30])
            ->update([
                'monitoring_interval_minutes' => 60,
            ]);

        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedSmallInteger('monitoring_interval_minutes')
                ->default(60)
                ->change();
        });
    }
};
