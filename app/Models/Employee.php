<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'position_id',
        'section_id',
        'division_id',
        'is_chief_of_hospital',
        'date_hired',
        'employment_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_hired'           => 'date',
            'is_chief_of_hospital' => 'boolean',
            'is_active'            => 'boolean',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ISANG plantilla position lang - pinanggagalingan ng CORE functions. */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** Lahat ng designations, kasama ang mga tapos na. */
    public function designations(): BelongsToMany
    {
        return $this->belongsToMany(Designation::class, 'employee_designations')
            ->withPivot(['start_date', 'end_date', 'order_reference', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Kasalukuyang hawak na designations - pwedeng higit sa isa nang sabay.
     * Dito galing ang STRATEGIC at SUPPORT functions na pwedeng piliin.
     */
    public function activeDesignations(): BelongsToMany
    {
        return $this->designations()->wherePivot('is_active', true);
    }

    /** Kung siya ang naka-assign na Section Head, ito ang section na hawak niya. */
    public function headedSection(): HasOne
    {
        return $this->hasOne(Section::class, 'section_head_employee_id');
    }

    /** Kung siya ang naka-assign na Division Head, ito ang division na hawak niya. */
    public function headedDivision(): HasOne
    {
        return $this->hasOne(Division::class, 'division_head_employee_id');
    }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class);
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    protected function fullName(): Attribute
    {
        return Attribute::get(fn(): string => collect([
            $this->first_name,
            $this->middle_name ? mb_substr($this->middle_name, 0, 1) . '.' : null,
            $this->last_name,
            $this->suffix,
        ])->filter()->implode(' '));
    }

    /**
     * Ang totoong division ng empleyado.
     * Kung may section siya, galing sa section. Kung wala, yung direktang division_id.
     */
    protected function effectiveDivision(): Attribute
    {
        return Attribute::get(fn(): ?Division => $this->section?->division ?? $this->division);
    }

    // ---------------------------------------------------------------
    // Role checks (organizational, hindi Spatie roles)
    // ---------------------------------------------------------------

    public function isSectionHead(): bool
    {
        return $this->headedSection()->exists();
    }

    public function isDivisionHead(): bool
    {
        return $this->headedDivision()->exists();
    }

    public function isChiefOfHospital(): bool
    {
        return $this->is_chief_of_hospital;
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
