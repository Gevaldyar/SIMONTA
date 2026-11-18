<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Technology extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'vendor',
        'website_url',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Satu teknologi dapat memiliki banyak aturan deteksi.
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(TechnologySignature::class);
    }

    /**
     * Satu teknologi dapat ditemukan dalam banyak pemeriksaan.
     */
    public function scanTechnologies(): HasMany
    {
        return $this->hasMany(ScanTechnology::class);
    }
}
