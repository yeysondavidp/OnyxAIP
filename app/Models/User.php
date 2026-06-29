<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int|null $client_id Null for PM (sees all clients); set for client_user (v2).
 * @property UserRole $role
 */
#[Fillable(['name', 'email', 'password', 'client_id', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, CanResetPassword, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
        ];
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isPm(): bool
    {
        return $this->role === UserRole::Pm;
    }

    public function isTechnician(): bool
    {
        return $this->role === UserRole::Technician;
    }

    public function isClientUser(): bool
    {
        return $this->role === UserRole::ClientUser;
    }

    // ── Tenant scope ──────────────────────────────────────────────────────────

    /**
     * Returns the client IDs this user is permitted to see.
     * null  → unrestricted (PM, sees all clients).
     * array → restricted to those IDs (client_user in v2).
     */
    public function permittedClientIds(): ?array
    {
        if ($this->role === UserRole::ClientUser && $this->client_id !== null) {
            return [(int) $this->client_id];
        }

        return null; // PM: unrestricted
    }
}
