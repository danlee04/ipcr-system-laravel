<?php

namespace App\Models;

use App\Enums\RatingMeasure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the employee reported for one measure on one IPCR line.
 *
 * The count and total are kept alongside the percentage they make, because
 * the accomplishment sentence says "12/12" and that cannot be recovered
 * from 100% afterwards.
 */
class IpcrItemMeasure extends Model
{
    protected $fillable = ['ipcr_item_id', 'measure', 'value', 'reported_count', 'reported_total'];

    protected function casts(): array
    {
        return [
            'measure'        => RatingMeasure::class,
            'value'          => 'decimal:2',
            'reported_count' => 'decimal:2',
            'reported_total' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(IpcrItem::class, 'ipcr_item_id');
    }
}
