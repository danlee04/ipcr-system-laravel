<?php

namespace App\Models;

use App\Enums\FunctionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master catalog entry. HINDI ito ang IPCR line item - panukala lang
 * na pwedeng piliin ng empleyado kapag manu-mano siyang nag-a-add sa IPCR niya.
 */
class JobFunction extends Model
{
    protected $fillable = [
        'position_id',
        'designation_id',
        'category',
        'title',
        'success_indicator',
        'default_weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category'       => FunctionCategory::class,
            'default_weight' => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfCategory(Builder $query, FunctionCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    /** Ang open pool - walang kabit na position o designation. */
    public function scopeCommon(Builder $query): Builder
    {
        return $query->where('category', FunctionCategory::Common);
    }
}
