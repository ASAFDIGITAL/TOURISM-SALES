<?php
namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $tenantId = $user->getActiveTenantId();

            if ($tenantId && ($user->role === 'agent' || $user->isImpersonatingTenant())) {
                $builder->where($model->getTable() . '.tenant_id', $tenantId);
            }
        }
    }
}
