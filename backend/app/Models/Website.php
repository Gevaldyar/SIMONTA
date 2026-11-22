<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Website extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'opd_id',
        'name',
        'url',
        'description',
        'is_active',
        'monitoring_enabled',
        'monitoring_interval_minutes',
    ];

    protected $attributes = [
        'is_active' => true,
        'monitoring_enabled' => true,
        'monitoring_interval_minutes' => 10,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'monitoring_enabled' => 'boolean',
        'monitoring_interval_minutes' => 'integer',
        'next_scan_at' => 'datetime',
    ];
    

    /**
     * Website dimiliki oleh satu OPD.
     */
    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    /**
     * Satu website dapat memiliki banyak riwayat pemeriksaan.
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }
    /**
 * Riwayat pemeriksaan registrasi domain.
 */
public function domainRegistrationChecks(): HasMany
{
    return $this->hasMany(DomainRegistrationCheck::class);
}

/**
 * Riwayat pemeriksaan sertifikat SSL.
 */
public function sslCertificateChecks(): HasMany
{
    return $this->hasMany(SslCertificateCheck::class);
}
/**
 * Hasil pemeriksaan registrasi domain terbaru.
 */
public function latestDomainRegistrationCheck(): HasOne
{
    return $this->hasOne(DomainRegistrationCheck::class)
        ->latestOfMany('checked_at');
}

/**
 * Hasil pemeriksaan sertifikat SSL terbaru.
 */
public function latestSslCertificateCheck(): HasOne
{
    return $this->hasOne(SslCertificateCheck::class)
        ->latestOfMany('checked_at');
}
}
