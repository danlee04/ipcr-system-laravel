<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $fillable = [
        'name',
        'code',
        'division_head_employee_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** Routing lang ang gamit nito - walang epekto sa IPCR functions ng head. */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'division_head_employee_id');
    }

    /** Mga nakatalaga nang direkta sa division (hal. ang Division Head mismo). */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
