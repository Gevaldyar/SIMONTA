<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'status',
        'scheduled_for',
        'started_at',
        'completed_at',
        'http_status_code',
        'response_time_ms',
        'final_url',
        'page_title',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'http_status_code' => 'integer',
        'response_time_ms' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Pemeriksaan dimiliki oleh satu website.
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * Satu pemeriksaan dapat menemukan banyak teknologi.
     */
    public function scanTechnologies(): HasMany
    {
        return $this->hasMany(ScanTechnology::class);
    }
}
