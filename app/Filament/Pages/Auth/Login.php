<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $data = $this->form->getState();
            $user = \App\Models\User::where('email', $data['email'])->first();

            if ($user && $user->role === 'agent' && $user->tenant && $user->tenant->status !== 'active') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'data.email' => __('ui.suspended_message'),
                ]);
            }

            throw $e;
        }
    }

    /**
     * @return class-string<LoginResponse>
     */
    protected function getRedirectUrl(): string
    {
        $user = auth()->user();

        if ($user->role === 'super_admin') {
            return '/';
        }

        if ($user->role === 'agent') {
            return '/agent';
        }

        return parent::getRedirectUrl();
    }
}
