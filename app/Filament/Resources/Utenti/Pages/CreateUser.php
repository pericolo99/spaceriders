<?php

namespace App\Filament\Resources\Utenti\Pages;

use App\Filament\Resources\Utenti\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
