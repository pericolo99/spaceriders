<?php

namespace App\Filament\Resources\Utenti\Pages;

use App\Filament\Resources\Utenti\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->is(Auth::user()))];
    }
}
