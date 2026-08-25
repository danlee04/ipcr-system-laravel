<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Naka-model ang pivot para may sarili itong CRUD screen sa HR
 * (pag-assign at pagtatapos ng OIC designations, may Office Order reference).
 */
class EmployeeDesignation extends Model
{
    protected $table = 'employee_designations';

    protected $fillable = [
        'employee_id',
        'designation_id',
        'start_date',
        'end_date',
        'order_reference',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'is_active'  => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
