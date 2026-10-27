<?php

namespace App\Models;

use App\Support\Nominativo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clienti';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_nascita' => 'date',
        ];
    }

    protected function nome(): Attribute
    {
        return Attribute::set(fn (?string $valore): ?string => Nominativo::capitalizza($valore));
    }

    protected function cognome(): Attribute
    {
        return Attribute::set(fn (?string $valore): ?string => Nominativo::capitalizza($valore));
    }

    /** «Cognome Nome», come si cerca e si legge al banco. */
    protected function nominativo(): Attribute
    {
        return Attribute::get(fn (): string => trim(($this->cognome ?? '').' '.($this->nome ?? '')) ?: 'Cliente #'.$this->id);
    }

    public function noleggi(): HasMany
    {
        return $this->hasMany(Noleggio::class);
    }
}
