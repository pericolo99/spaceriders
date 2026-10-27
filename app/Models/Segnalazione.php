<?php

namespace App\Models;

use App\Enums\Esito;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segnalazione extends Model
{
    protected $table = 'segnalazioni';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'esito' => Esito::class,
            'foto' => 'array',
            'avvenuta_at' => 'datetime',
            'risolta_at' => 'datetime',
        ];
    }

    public function attrezzatura(): BelongsTo
    {
        return $this->belongsTo(Attrezzatura::class);
    }

    public function noleggio(): BelongsTo
    {
        return $this->belongsTo(Noleggio::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
