<?php

namespace App\Models;

use App\Enums\StatoAttrezzatura;
use App\Enums\Tipologia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Attrezzatura extends Model
{
    use HasFactory;

    protected $table = 'attrezzature';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipologia' => Tipologia::class,
            'stato' => StatoAttrezzatura::class,
            'data_acquisto' => 'date',
        ];
    }

    /** «Rossignol Experience 82 · 170», senza ripetere ciò che manca. */
    protected function nome(): Attribute
    {
        return Attribute::get(function (): string {
            $nome = trim(implode(' ', array_filter([$this->marca, $this->modello])));

            // Senza marca né modello la tipologia da sola («Altro») non dice quale pezzo è.
            $nome = $nome ?: ($this->tipologia === Tipologia::Altro || ! $this->tipologia
                ? "Pezzo {$this->codice}"
                : $this->tipologia->getLabel());

            return implode(' · ', array_filter([$nome, $this->misura]));
        });
    }

    /** La foto del prodotto se c'è, altrimenti l'icona della sua tipologia (public/img/tipologie). */
    public function immagine(): string
    {
        return $this->foto
            ? Storage::disk('public')->url($this->foto)
            : asset('img/tipologie/'.strtolower(($this->tipologia ?? Tipologia::Altro)->value).'.svg');
    }

    /** Lo sfondo dietro l'immagine: neutro per le foto, verde chiaro per le icone a tratto. */
    public function sfondoImmagine(): string
    {
        return $this->foto ? 'bg-gray-50' : 'bg-primary-50';
    }

    public function righe(): HasMany
    {
        return $this->hasMany(RigaNoleggio::class);
    }

    /** La riga del noleggio in cui il pezzo è fuori adesso, se lo è. */
    public function rigaAperta(): HasOne
    {
        return $this->hasOne(RigaNoleggio::class)->whereNull('rientrata_at')->latestOfMany();
    }

    public function segnalazioni(): HasMany
    {
        return $this->hasMany(Segnalazione::class);
    }

    /**
     * Il pezzo che corrisponde a un codice letto: prima il barcode, poi il codice
     * gestionale. Quest'ultimo vale solo se è di un pezzo solo: nel vecchio
     * inventario alcuni sono condivisi, e indovinare sarebbe peggio di non trovare.
     */
    public static function perCodice(string $codice): ?self
    {
        $codice = trim($codice);

        if ($codice === '') {
            return null;
        }

        if ($pezzo = static::where('codice', $codice)->first()) {
            return $pezzo;
        }

        $perGestionale = static::where('codice_gestionale', $codice)->limit(2)->get();

        return $perGestionale->count() === 1 ? $perGestionale->first() : null;
    }

    public function isFuori(): bool
    {
        return $this->rigaAperta()->exists();
    }

    public function isDisponibile(): bool
    {
        return $this->stato === StatoAttrezzatura::Attiva && ! $this->isFuori();
    }

    /**
     * Ricerca per i suggerimenti mentre si digita: ogni parola deve comparire
     * nel codice, nel codice gestionale, nella marca, nel modello, nella misura
     * o nella tipologia. «rossignol 170» trova i Rossignol da 170, «sci head»
     * gli sci Head.
     */
    public function scopeCerca(Builder $query, string $testo): Builder
    {
        foreach (preg_split('/\s+/u', trim($testo), -1, PREG_SPLIT_NO_EMPTY) as $parola) {
            // «sci», «casco», «scarponi»: la parola può essere anche la tipologia.
            $tipologie = array_values(array_filter(
                Tipologia::cases(),
                fn (Tipologia $t) => str_contains(mb_strtolower($t->getLabel().' '.$t->plurale()), mb_strtolower($parola)),
            ));
            $parola = addcslashes($parola, '%_\\');
            $query->where(fn (Builder $q) => $q
                ->where('codice', 'like', "{$parola}%")
                ->orWhere('codice_gestionale', 'like', "{$parola}%")
                ->orWhere('marca', 'like', "%{$parola}%")
                ->orWhere('modello', 'like', "%{$parola}%")
                ->orWhere('misura', $parola)
                ->when($tipologie, fn (Builder $q) => $q->orWhereIn('tipologia', $tipologie)));
        }

        return $query;
    }

    public function scopeFuori(Builder $query): Builder
    {
        return $query->whereHas('righe', fn (Builder $r) => $r->whereNull('rientrata_at'));
    }

    public function scopeDisponibili(Builder $query): Builder
    {
        return $query->where('stato', StatoAttrezzatura::Attiva)
            ->whereDoesntHave('righe', fn (Builder $r) => $r->whereNull('rientrata_at'));
    }
}
