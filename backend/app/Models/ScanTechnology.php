<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanTechnology extends Model
{
    use HasFactory;

    protected $table = 'scan_technologies';

    protected $fillable = [
        'scan_id',
        'technology_id',
        'signature_id',
        'version',
        'confidence',
        'evidence',
    ];

    protected $casts = [
        'confidence' => 'integer',
        'evidence' => 'array',
    ];

    /**
     * Hasil identifikasi berasal dari satu pemeriksaan.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * Hasil identifikasi mengacu pada satu teknologi.
     */
    public function technology(): BelongsTo
    {
        return $this->belongsTo(Technology::class);
    }

    /**
     * Hasil identifikasi dapat mengacu pada aturan deteksi.
     */
    public function signature(): BelongsTo
    {
        return $this->belongsTo(
            TechnologySignature::class,
            'signature_id'
        );
    }
}
