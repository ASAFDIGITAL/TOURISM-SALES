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
                $tenantId = $user->getActiveTenantId();
                // Automatically assign tenant_id if not set manually
                if (!$model->tenant_id && $tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
