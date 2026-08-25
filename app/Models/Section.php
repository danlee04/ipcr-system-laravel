<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = [
        'division_id',
        'name',
        'code',
        'section_head_employee_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** Routing lang ang gamit nito - walang epekto sa IPCR functions ng head. */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'section_head_employee_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
