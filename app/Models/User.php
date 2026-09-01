<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The signed-in user's HR record. Nearly all IPCR logic goes through it -
     * when this is null, no employee record is linked to the account and the
     * user cannot create an IPCR.
     */
    /**
     * Is there a dashboard for this account?
     *
     * A dashboard answers "how is everybody doing". For somebody with nobody
     * under them that is a page about themselves, and their own IPCR says all
     * of it faster - so they do not get one. Heads have people to look after;
     * admin and HR look after the hospital.
     */
    public function seesDashboard(): bool
    {
        return $this->hasAnyRole(['admin', 'hr'])
            || (bool) $this->employee?->holdsApprovingPost();
    }

    /**
     * Where this account belongs when it has not asked for anywhere.
     *
     * Signing in, and typing the bare address. An account with neither a
     * dashboard nor an employee record - a fresh login HR has not finished
     * setting up - would be turned away from both, so it goes to the profile,
     * which always exists.
     */
    public function landingRoute(): string
    {
        if ($this->seesDashboard()) {
            return 'dashboard';
        }

        return $this->employee ? 'ipcrs.index' : 'profile.edit';
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}
