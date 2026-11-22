<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainRegistrationCheck extends Model
{
    protected $fillable = [
        'website_id',
        'domain',
        'status',
        'registrar',
        'registered_at',
        'expires_at',
        'days_remaining',
        'nameservers',
        'rdap_server',
        'checked_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'expires_at' => 'datetime',
        'days_remaining' => 'integer',
        'nameservers' => 'array',
        'checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
