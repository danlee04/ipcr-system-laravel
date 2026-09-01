<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An extra assignment an employee holds beyond their plantilla position -
 * e.g. "OIC - Budget". This is where the selectable STRATEGIC and SUPPORT
 * functions come from, and an employee may hold more than one at a time.
 */
class Designation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'division_id',
        'section_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Does this designation post whoever holds it somewhere?
     *
     * Most do. One that names no office is a title and nothing more, and
     * leaves the holder where their plantilla position put them.
     */
    public function postsElsewhere(): bool
    {
        return $this->division_id !== null || $this->section_id !== null;
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
