<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

/**
 * Si entra con il nome utente o con l'email: nel vecchio pannello la colonna
 * `email` degli utenti contiene spesso un nome senza chiocciola, e quegli
 * accessi devono continuare a funzionare. La colonna resta `email`.
 */
class Accesso extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Utente')
            ->helperText('Il nome utente o l’email con cui accedi.')
            ->required()
            ->autocomplete('username')
            ->extraInputAttributes(['autocapitalize' => 'none', 'spellcheck' => 'false'])
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
        ];
    }
}
