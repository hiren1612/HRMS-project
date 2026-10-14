<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Available roles in the HRMS. Using constants keeps role names in one
     * place and prevents typo bugs (e.g. 'admmin') across the codebase.
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_HR = 'hr';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_EMPLOYEE = 'employee';

    /**
     * The complete list of valid roles (useful for validation / dropdowns).
     *
     * @var list<string>
     */
    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_HR,
        self::ROLE_MANAGER,
        self::ROLE_EMPLOYEE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
     * Determine if the user has the given single role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Determine if the user's role matches any of the given roles.
     *
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Convenience check for the highest-privilege role.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * The employee (HR) profile for this user, if one exists.
     * Inverse of Employee::user (one-to-one).
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Public URL of the uploaded profile photo, or null if none set.
     */
    public function profilePhotoUrl(): ?string
    {
        // QA H8: a path can outlive its file (storage not carried across a
        // deploy). Returning a URL for a missing file renders a broken image;
        // returning null lets every view fall back to the initials avatar.
        if (! $this->profile_photo_path || ! Storage::disk('public')->exists($this->profile_photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }

    /**
     * First letter of the name, used for the initials avatar fallback.
     */
    public function initial(): string
    {
        return strtoupper(substr($this->name, 0, 1));
    }
}
