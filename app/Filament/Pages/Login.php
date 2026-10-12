<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as FilamentLogin;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

class Login extends FilamentLogin
{
    public function getTitle(): string | Htmlable
    {
        return 'Masuk';
    }

    public function getHeading(): string | Htmlable | null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getHeading()
            : 'Selamat datang kembali.';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return parent::getSubheading() ?? 'Masuk dengan akun yang telah terdaftar untuk melanjutkan pekerjaan Anda.';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email akun')
            ->autofocus(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->label('Password');
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()->label('Ingat saya');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Masuk ke SIMAWAR');
    }
}
