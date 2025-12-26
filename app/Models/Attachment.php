<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Attachment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'trip_id',
        'name',
        'type',
        'path',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
