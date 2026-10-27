<?php

namespace App\Filament\Resources\Segnalazioni;

use App\Enums\Esito;
use App\Enums\StatoAttrezzatura;
use App\Filament\Resources\Attrezzature\AttrezzaturaResource;
use App\Filament\Resources\Segnalazioni\Pages\ListSegnalazioni;
use App\Models\Segnalazione;
use App\Noleggi\Banco;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Danni, dubbi e pezzi persi. Nascono al rientro; si chiudono rimettendo il
 * pezzo in servizio.
 */
class SegnalazioneResource extends Resource
{
    protected static ?string $model = Segnalazione::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Segnalazioni';

    protected static ?string $modelLabel = 'segnalazione';

    protected static ?string $pluralModelLabel = 'segnalazioni';

    protected static ?string $slug = 'segnalazioni';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['attrezzatura', 'cliente', 'operatore']))
            ->defaultSort('avvenuta_at', 'desc')
            ->columns([
                TextColumn::make('avvenuta_at')->label('Quando')->dateTime('d/m/Y')->sortable(),
                TextColumn::make('attrezzatura.nome')
                    ->label('Pezzo')
                    ->weight('semibold')
                    ->description(fn (Segnalazione $s): string => $s->attrezzatura->codice)
                    ->url(fn (Segnalazione $s): string => AttrezzaturaResource::getUrl('edit', ['record' => $s->attrezzatura_id])),
                TextColumn::make('esito')->label('Che cosa')->badge(),
                TextColumn::make('cliente.nominativo')->label('Cliente')->placeholder('—'),
                TextColumn::make('note')->label('Note')->wrap()->limit(80)->placeholder(''),
                ImageColumn::make('foto')->label('Foto')->disk('public')->stacked()->limit(3)->imageHeight(40),
                TextColumn::make('risolta_at')->label('Risolta')->date('d/m/Y')->placeholder('aperta'),
            ])
            ->filters([
                TernaryFilter::make('aperte')
                    ->label('Aperte')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('risolta_at'),
                        false: fn (Builder $query) => $query->whereNotNull('risolta_at'),
                    ),
                SelectFilter::make('esito')->label('Che cosa')->options(Esito::class),
            ])
            ->recordActions([
                Action::make('rimetti')
                    ->label('Rimetti in servizio')
                    ->icon('heroicon-m-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (Segnalazione $s): bool => $s->risolta_at === null && $s->attrezzatura->stato !== StatoAttrezzatura::Attiva)
                    ->action(function (Segnalazione $s) {
                        app(Banco::class)->rimettiInServizio($s->attrezzatura);
                        Notification::make()->title('Di nuovo in servizio')->body($s->attrezzatura->nome)->success()->send();
                    }),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Segnalazione::whereNull('risolta_at')
            ->whereHas('attrezzatura', fn (Builder $a) => $a->where('stato', StatoAttrezzatura::DaControllare))
            ->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pezzi da controllare';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSegnalazioni::route('/'),
        ];
    }
}
