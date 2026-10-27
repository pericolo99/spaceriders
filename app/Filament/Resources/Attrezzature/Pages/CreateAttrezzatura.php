<?php

namespace App\Filament\Resources\Attrezzature\Pages;

use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAttrezzatura extends CreateRecord
{
    protected static string $resource = AttrezzaturaResource::class;

    /** Si caricano di solito a serie: dopo il salvataggio si resta sul modulo vuoto. */
    protected static bool $canCreateAnother = true;

    protected function afterCreate(): void
    {
        $altri = AttrezzaturaResource::propagaFoto($this->getRecord(), (bool) ($this->data['foto_per_modello'] ?? false), nuovo: true);

        if ($altri) {
            Notification::make()->title('Foto applicata al modello')->body("La stessa foto ora vale anche per altri {$altri} pezzi.")->success()->send();
        }
    }
}
