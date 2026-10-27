<?php

namespace App\Noleggi;

use App\Enums\StatoAttrezzatura;
use App\Models\Attrezzatura;
use App\Models\Noleggio;
use App\Models\RigaNoleggio;
use Illuminate\Database\Eloquent\Collection;

/**
 * I numeri e le liste della giornata, per la pagina «Oggi» del pannello e
 * del telefono. Si contano i noleggi, non i pezzi: al banco si ragiona per
 * cliente.
 */
class Oggi
{
    /** Noleggi consegnati oggi. */
    public function consegne(): int
    {
        return Noleggio::whereDate('data_consegna', today())->count();
    }

    /** Noleggi con il rientro previsto oggi: [quanti già chiusi, quanti in tutto]. */
    public function rientri(): array
    {
        $previsti = Noleggio::rientroOggi();

        return [(clone $previsti)->chiusi()->count(), $previsti->count()];
    }

    public function inRitardo(): int
    {
        return Noleggio::inRitardo()->count();
    }

    /** Pezzi noleggiabili adesso: [liberi, in servizio]. */
    public function attrezzaturaLibera(): array
    {
        return [
            Attrezzatura::disponibili()->count(),
            Attrezzatura::where('stato', StatoAttrezzatura::Attiva)->count(),
        ];
    }

    public function daControllare(): int
    {
        return Attrezzatura::where('stato', StatoAttrezzatura::DaControllare)->count();
    }

    /** I noleggi aperti che devono tornare oggi, poi quelli in ritardo dal più vecchio. */
    public function daFare(int $quanti = 12): Collection
    {
        return Noleggio::aperti()
            ->whereDate('data_rientro', '<=', today())
            ->with('cliente')
            ->withCount('righeAperte')
            ->orderByRaw('data_rientro = ? desc', [today()->toDateString()])
            ->orderBy('data_rientro')
            ->limit($quanti)
            ->get();
    }

    /** Gli ultimi pezzi passati dal banco oggi, in entrambi i sensi. */
    public function movimenti(int $quanti = 8): Collection
    {
        return RigaNoleggio::query()
            ->where(fn ($q) => $q->where('created_at', '>=', today())->orWhere('rientrata_at', '>=', today()))
            ->with(['attrezzatura', 'noleggio.cliente'])
            ->latest('updated_at')
            ->limit($quanti)
            ->get();
    }
}
