<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrPeriod extends Model
{
    use HasFactory;

    protected $table = 'ipcr_periods';

    protected $fillable = [
        'name',
        'year',
        'type',
        'start_date',
        'end_date',
        'submission_deadline',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year'                => 'integer',
            'start_date'          => 'date',
            'end_date'            => 'date',
            'submission_deadline' => 'date',
        ];
    }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * The one period IPCRs are created against.
     *
     * There is exactly one, because opening a period closes whichever was
     * open - see PeriodController. The ordering is a safety net for a row left
     * open by a seeder or by hand, not the mechanism: without it the answer
     * would depend on row order, and two periods starting on the same day
     * would make it a coin toss.
     */
    public static function active(): ?self
    {
        return static::query()
            ->open()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
