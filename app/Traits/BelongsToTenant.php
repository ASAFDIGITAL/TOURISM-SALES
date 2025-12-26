<?php
namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (Auth::check()) {
                $user = Auth::user();
                // Automatically assign tenant_id if not set manually
                if (!$model->tenant_id && $user->tenant_id) {
                    $model->tenant_id = $user->tenant_id;
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
