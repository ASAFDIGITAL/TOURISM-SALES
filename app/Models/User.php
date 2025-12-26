<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
        'language',
        'currency',
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
    
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        if ($this->role === 'agent') {
            // Check tenant status
            if ($this->tenant && $this->tenant->status !== 'active') {
                return false;
            }

            // Allow access to agent panel
            if ($panel->getId() === 'agent') {
                return $this->tenant_id !== null;
            }
            
            // Allow temporary access to admin panel (root) so they can be redirected to /agent
            if ($panel->getId() === 'admin') {
                return true;
            }
        }

        return false;
    }
    
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
