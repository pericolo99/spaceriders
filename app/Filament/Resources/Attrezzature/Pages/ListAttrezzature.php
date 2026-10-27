<?php

namespace App\Filament\Resources\Attrezzature\Pages;

use App\Enums\StatoAttrezzatura;
use App\Enums\Tipologia;
use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
use App\Models\Attrezzatura;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListAttrezzature extends ListRecords
{
    protected static string $resource = AttrezzaturaResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Inventario';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $disponibili = Attrezzatura::disponibili()->count();
        $fuori = Attrezzatura::fuori()->count();
        $daControllare = Attrezzatura::where('stato', StatoAttrezzatura::DaControllare)->count();

        return "{$disponibili} pezzi disponibili, {$fuori} a noleggio, {$daControllare} da controllare.";
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Aggiungi attrezzatura')];
    }

    /** Una scheda per famiglia, più quelle dei pezzi che hanno bisogno di attenzione. */
    public function getTabs(): array
    {
        $conteggi = Attrezzatura::where('stato', '!=', StatoAttrezzatura::FuoriServizio)
            ->selectRaw('tipologia, count(*) as n')->groupBy('tipologia')->pluck('n', 'tipologia');

        $schede = ['tutto' => Tab::make('Tutto')->modifyQueryUsing(fn (Builder $query) => $query->where('stato', '!=', StatoAttrezzatura::FuoriServizio))];

        foreach (Tipologia::cases() as $tipologia) {
            if ($n = $conteggi->get($tipologia->value)) {
                $schede[$tipologia->value] = Tab::make($tipologia->plurale())
                    ->badge($n)
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('tipologia', $tipologia)->where('stato', '!=', StatoAttrezzatura::FuoriServizio));
            }
        }

        $schede['da_controllare'] = Tab::make('Da controllare')
            ->badge(Attrezzatura::where('stato', StatoAttrezzatura::DaControllare)->count() ?: null)
            ->badgeColor('gray')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('stato', StatoAttrezzatura::DaControllare));

        $schede['fuori_servizio'] = Tab::make('Fuori servizio')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('stato', StatoAttrezzatura::FuoriServizio));

        return $schede;
    }
}
