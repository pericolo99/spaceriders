<?php

namespace App\Filament\Resources\Utenti\Pages;

use App\Filament\Resources\Utenti\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuovo utente')];
    }
}
