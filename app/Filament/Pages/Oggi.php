<?php

namespace App\Filament\Pages;

use App\Noleggi\Oggi as LetturaDiOggi;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * La home: i numeri della giornata, le due azioni del banco e i noleggi che
 * devono tornare oggi o sono in ritardo.
 */
class Oggi extends Dashboard
{
    protected string $view = 'filament.pages.oggi';

    protected static ?string $title = 'Oggi';

    protected static ?string $navigationLabel = 'Oggi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    /** tutto · oggi · ritardo */
    #[Url]
    public string $filtro = 'tutto';

    /** Dalle scorciatoie dell'app sul telefono: /?apri=consegna, /?apri=rientro. */
    public function mount(): void
    {
        $apri = request()->query('apri');

        if (in_array($apri, ['consegna', 'rientro', 'nuovo-cliente'], true)) {
            $this->dispatch("apri-{$apri}");
        }
    }

    /** Una consegna o un rientro appena registrati cambiano i numeri della giornata. */
    #[On('operazione-completata')]
    public function aggiorna(): void {}

    public function getHeading(): string|Htmlable
    {
        $nome = str(Auth::user()?->name)->before(' ')->toString();

        return $nome !== '' ? "Buongiorno, {$nome}" : 'Buongiorno';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return ucfirst(today()->translatedFormat('l j F'));
    }

    public function getViewData(): array
    {
        $oggi = app(LetturaDiOggi::class);

        $daFare = $oggi->daFare(30);
        $daFare = match ($this->filtro) {
            'oggi' => $daFare->filter(fn ($n) => $n->data_rientro->isToday()),
            'ritardo' => $daFare->filter(fn ($n) => $n->isInRitardo()),
            default => $daFare,
        };

        return [
            'consegne' => $oggi->consegne(),
            'rientri' => $oggi->rientri(),
            'inRitardo' => $oggi->inRitardo(),
            'libera' => $oggi->attrezzaturaLibera(),
            'daControllare' => $oggi->daControllare(),
            'daFare' => $daFare->take(12),
        ];
    }
}
