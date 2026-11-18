<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnologySignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'technology_id',
        'name',
        'type',
        'target',
        'pattern',
        'match_type',
        'version_pattern',
        'confidence',
        'is_active',
    ];

    protected $casts = [
        'confidence' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Aturan deteksi dimiliki oleh satu teknologi.
     */
    public function technology(): BelongsTo
    {
        return $this->belongsTo(Technology::class);
    }

    /**
     * Satu aturan deteksi dapat digunakan dalam banyak hasil scan.
     */
    public function scanTechnologies(): HasMany
    {
        return $this->hasMany(ScanTechnology::class, 'signature_id');
    }
}
