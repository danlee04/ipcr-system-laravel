<?php

namespace App\Models;

use App\Enums\OrgPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory;
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

    /** Exactly ONE plantilla position - the source of CORE functions. */
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

    /** Every designation, including ones that have ended. */
    public function designations(): BelongsToMany
    {
        return $this->belongsToMany(Designation::class, 'employee_designations')
            ->withPivot(['start_date', 'end_date', 'order_reference', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Designations currently held - there may be more than one at a time.
     * These are the source of the selectable STRATEGIC and SUPPORT functions.
     */
    public function activeDesignations(): BelongsToMany
    {
        return $this->designations()->wherePivot('is_active', true);
    }

    /** If they are the assigned Section Head, this is the section they lead. */
    public function headedSection(): HasOne
    {
        return $this->hasOne(Section::class, 'section_head_employee_id');
    }

    /** If they are the assigned Division Head, this is the division they lead. */
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
     * The employee's effective division.
     * Taken from their section when they have one, otherwise from division_id.
     */
    protected function effectiveDivision(): Attribute
    {
        return Attribute::get(fn(): ?Division => $this->section?->division ?? $this->division);
    }

    // ---------------------------------------------------------------
    // Role checks (organizational, not Spatie roles)
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

    /**
     * Does this employee hold a post that IPCRs get routed to?
     *
     * Asked of the post, not of the inbox. A newly appointed section head has
     * nothing routed to them yet, and before the first submission of a cycle
     * that is true of every head - so a check based on routed IPCRs would hide
     * the inbox from exactly the people who need to find it.
     */
    public function holdsApprovingPost(): bool
    {
        return $this->isSectionHead()
            || $this->isDivisionHead()
            || $this->isChiefOfHospital();
    }

    /** The approving post they hold, read off the org chart. */
    public function post(): ?OrgPost
    {
        return OrgPost::heldBy($this);
    }

    /**
     * The approving post they hold, for showing beside their name.
     *
     * An IPCR names people, but the flow is about posts: the Section Head
     * assesses and the Division Head gives the final approval. Without the
     * post beside the name, nobody can tell whether an IPCR went where it
     * should have.
     */
    public function postTitle(): ?string
    {
        return $this->post()?->label();
    }

    /** Their name with the post that explains their place in a chain. */
    public function nameWithPost(): string
    {
        $post = $this->postTitle();

        return $post === null ? $this->full_name : "{$this->full_name} — {$post}";
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
