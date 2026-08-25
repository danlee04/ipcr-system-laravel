<?php

namespace App\Models;

use App\Enums\FunctionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = [
        'title',
        'item_number',
        'salary_grade',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'salary_grade' => 'integer',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function jobFunctions(): HasMany
    {
        return $this->hasMany(JobFunction::class);
    }

    /** Ang CORE functions na kayang piliin ng may hawak ng position na ito. */
    public function coreFunctions(): HasMany
    {
        return $this->jobFunctions()
            ->where('category', FunctionCategory::Core)
            ->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
