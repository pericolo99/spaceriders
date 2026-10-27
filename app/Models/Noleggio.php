<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Noleggio extends Model
{
    use HasFactory;

    protected $table = 'noleggi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_consegna' => 'date',
            'data_rientro' => 'date',
            'chiuso_at' => 'datetime',
            'giorni_extra' => 'integer',
            'sacche_sci' => 'integer',
            'sacche_snow' => 'integer',
            'sacche_scarponi' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function righe(): HasMany
    {
        return $this->hasMany(RigaNoleggio::class);
    }

    public function righeAperte(): HasMany
    {
        return $this->righe()->whereNull('rientrata_at');
    }

    public function segnalazioni(): HasMany
    {
        return $this->hasMany(Segnalazione::class);
    }

    public function isAperto(): bool
    {
        return $this->chiuso_at === null;
    }

    public function isInRitardo(): bool
    {
        return $this->isAperto() && $this->data_rientro->lt(today());
    }

    /** Giorni di ritardo sul rientro previsto, zero se non è in ritardo. */
    public function giorniDiRitardo(): int
    {
        return $this->isInRitardo() ? (int) $this->data_rientro->diffInDays(today()) : 0;
    }

    /** Le sacche consegnate, in una riga: «2 sci · 1 scarponi». */
    public function sacche(): ?string
    {
        $sacche = array_filter([
            'sci' => $this->sacche_sci,
            'snow' => $this->sacche_snow,
            'scarponi' => $this->sacche_scarponi,
        ]);

        return $sacche
            ? implode(' · ', array_map(fn ($n, $tipo) => "$n $tipo", $sacche, array_keys($sacche)))
            : null;
    }

    public function scopeAperti(Builder $query): Builder
    {
        return $query->whereNull('chiuso_at');
    }

    public function scopeChiusi(Builder $query): Builder
    {
        return $query->whereNotNull('chiuso_at');
    }

    public function scopeInRitardo(Builder $query): Builder
    {
        return $query->aperti()->whereDate('data_rientro', '<', today());
    }

    public function scopeRientroOggi(Builder $query): Builder
    {
        return $query->whereDate('data_rientro', today());
    }
}
