<?php

namespace App\Models;

use App\Enums\Esito;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un pezzo consegnato dentro un noleggio: aperta finché non rientra. */
class RigaNoleggio extends Model
{
    protected $table = 'noleggio_righe';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rientrata_at' => 'datetime',
            'esito' => Esito::class,
        ];
    }

    public function noleggio(): BelongsTo
    {
        return $this->belongsTo(Noleggio::class);
    }

    public function attrezzatura(): BelongsTo
    {
        return $this->belongsTo(Attrezzatura::class);
    }

    public function isAperta(): bool
    {
        return $this->rientrata_at === null;
    }
}
