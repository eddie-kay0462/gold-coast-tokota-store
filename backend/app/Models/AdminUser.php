<?php

namespace App\Models;

use App\Support\AdminCapability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'job_title',
        'avatar',
        'access_expires_at',
        'access_extensions',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'access_expires_at' => 'datetime',
            'last_active_at' => 'datetime',
            'access_extensions' => 'array',
        ];
    }

    /**
     * The single question every admin route asks. Roles are never compared
     * directly outside this class and `AdminCapability` — see the note there
     * on why.
     */
    public function hasCapability(string $capability): bool
    {
        if ($this->accessHasLapsed()) {
            return false;
        }

        return AdminCapability::allows($this->role, $capability);
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return $this->accessHasLapsed() ? [] : AdminCapability::for($this->role);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Admin *tier or above*. A Super Admin who could not do what an Admin can
     * would be a demotion dressed as a promotion.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    /**
     * Intern access is time-boxed. A lapsed account still authenticates — it
     * has to, or the person cannot see why they are locked out — but it holds
     * no capabilities at all until someone extends it.
     */
    public function accessHasLapsed(): bool
    {
        return $this->access_expires_at !== null && $this->access_expires_at->isPast();
    }

    public function workshopSessions(): HasMany
    {
        return $this->hasMany(WorkshopSession::class, 'created_by_admin_id');
    }
}
