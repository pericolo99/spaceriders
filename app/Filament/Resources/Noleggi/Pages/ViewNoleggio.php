<?php

namespace App\Filament\Resources\Noleggi\Pages;

use App\Filament\Resources\Noleggi\NoleggioResource;
use App\Noleggi\Banco;
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
            Action::make('pagato')
                ->label('Segna pagato')
                ->icon('heroicon-m-check')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->da_pagare)
                ->requiresConfirmation()
                ->modalHeading('Il cliente ha pagato?')
                ->modalDescription('Il promemoria «Da pagare» sparisce; la nota resta nella scheda.')
                ->modalSubmitActionLabel('Sì, ha pagato')
                ->action(function (): void {
                    app(Banco::class)->segnaPagato($this->getRecord());
                    $this->getRecord()->refresh();
                }),
            EditAction::make()->label('Correggi date e note')->color('gray'),
        ];
    }
}
