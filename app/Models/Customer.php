<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Customer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'passport_number',
        'address',
        'notes',
    ];

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }
}
