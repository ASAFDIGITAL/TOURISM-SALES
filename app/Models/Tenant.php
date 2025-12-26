<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'logo_path',
        'receipt_prefix',
        'receipt_next_number',
        'currency',
        'language',
        'joined_at',
        'expires_at',
        'subscription_amount',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
    
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
