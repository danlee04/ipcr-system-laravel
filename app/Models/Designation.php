<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Karagdagang atas na hawak ng empleyado bukod sa plantilla position niya -
 * hal. "OIC - Budget". Dito nanggagaling ang STRATEGIC at SUPPORT functions
 * na pwedeng piliin, at pwedeng higit sa isa ang hawak nang sabay.
 */
class Designation extends Model
{
    protected $fillable = [
        'title',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_designations')
            ->withPivot(['start_date', 'end_date', 'order_reference', 'is_active'])
            ->withTimestamps();
    }

    public function jobFunctions(): HasMany
    {
        return $this->hasMany(JobFunction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
