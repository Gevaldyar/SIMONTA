<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opd extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'opds';

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Satu OPD dapat memiliki banyak website.
     */
    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }
}
