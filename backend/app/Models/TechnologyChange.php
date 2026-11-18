<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnologyChange extends Model
{
    protected $fillable = [
        'scan_id',
        'previous_scan_id',
        'technology_id',
        'technology_name',
        'technology_slug',
        'change_type',
        'previous_version',
        'previous_confidence',
        'previous_evidence',
        'current_version',
        'current_confidence',
        'current_evidence',
    ];

    protected $casts = [
        'previous_confidence' => 'integer',
        'current_confidence' => 'integer',
        'previous_evidence' => 'array',
        'current_evidence' => 'array',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function previousScan(): BelongsTo
    {
        return $this->belongsTo(
            Scan::class,
            'previous_scan_id'
        );
    }

    public function technology(): BelongsTo
    {
        return $this->belongsTo(Technology::class);
    }
}