<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'client_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /**
     * Returns the client IDs this user is permitted to see.
     * null  → unrestricted (PM, sees all clients).
     * array → restricted to those IDs (client_user in v2).
     *
     * EPIC-01 (US-01.2) will refine this based on the role column
     * and per-PM client assignments.
     */
    public function permittedClientIds(): ?array
    {
        if ($this->client_id !== null) {
            return [(int) $this->client_id];
        }

        return null; // PM: unrestricted
    }
}
