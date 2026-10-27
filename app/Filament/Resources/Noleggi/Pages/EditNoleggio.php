<?php

namespace App\Filament\Resources\Noleggi\Pages;

use App\Filament\Resources\Noleggi\NoleggioResource;
use Filament\Resources\Pages\EditRecord;

class EditNoleggio extends EditRecord
{
    protected static string $resource = NoleggioResource::class;

    protected function getRedirectUrl(): string
    {
        return NoleggioResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
