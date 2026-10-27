<?php

namespace App\Filament\Resources\Attrezzature\Pages;

use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditAttrezzatura extends EditRecord
{
    protected static string $resource = AttrezzaturaResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->nome;
    }

    protected function getHeaderActions(): array
    {
        return [
            AttrezzaturaResource::rimettiInServizio()->record($this->getRecord()),
            // Un pezzo che è stato noleggiato non si cancella: si mette fuori servizio.
            DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->righe()->exists()),
        ];
    }

    protected function afterSave(): void
    {
        $altri = AttrezzaturaResource::propagaFoto($this->getRecord(), (bool) ($this->data['foto_per_modello'] ?? false));

        if ($altri) {
            Notification::make()->title('Foto applicata al modello')->body("La stessa foto ora vale anche per altri {$altri} pezzi.")->success()->send();
        }
    }
}
