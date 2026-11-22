<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificateCheck extends Model
{
    protected $fillable = [
        'website_id',
        'hostname',
        'status',
        'verification',
        'subject',
        'issuer',
        'valid_from',
        'valid_to',
        'days_remaining',
        'subject_alt_names',
        'fingerprint_sha256',
        'checked_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'days_remaining' => 'integer',
        'subject_alt_names' => 'array',
        'checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
