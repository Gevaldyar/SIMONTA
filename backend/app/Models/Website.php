<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'monitoring_interval_minutes' => 60,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'monitoring_enabled' => 'boolean',
        'monitoring_interval_minutes' => 'integer',
    ];

    /**
     * Website dimiliki oleh satu OPD.
     */
    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }
}
