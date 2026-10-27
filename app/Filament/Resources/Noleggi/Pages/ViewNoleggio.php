<?php

namespace App\Filament\Resources\Noleggi\Pages;

use App\Filament\Resources\Noleggi\NoleggioResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewNoleggio extends ViewRecord
{
    /** Dopo un rientro dalla finestra, la scheda e i suoi pezzi si aggiornano. */
    protected $listeners = ['operazione-completata' => '$refresh'];

    protected static string $resource = NoleggioResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Noleggio n. {$this->getRecord()->id}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rientro')
                ->label('Registra rientro')
                ->icon('heroicon-m-arrow-left')
                ->visible(fn (): bool => $this->getRecord()->isAperto())
                ->dispatch('apri-rientro-noleggio', fn (): array => ['noleggio' => $this->getRecord()->getKey()]),
            EditAction::make()->label('Correggi date e note')->color('gray'),
        ];
    }
}
