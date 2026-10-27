<?php

namespace Tests\Unit;

use App\Support\Nominativo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NominativoTest extends TestCase
{
    public static function nomi(): array
    {
        return [
            ['TERENZONI', 'Terenzoni'],
            ['canosa sofia', 'Canosa Sofia'],
            ["dell'amico", "Dell'Amico"],
            ["D'ANGELO", "D'Angelo"],
            ['de  luca ', 'De Luca'],
            ['rossi-bianchi', 'Rossi-Bianchi'],
            ['èlia', 'Èlia'],
            ['', null],
            [null, null],
        ];
    }

    #[DataProvider('nomi')]
    public function test_iniziali_maiuscole(?string $dato, ?string $atteso): void
    {
        $this->assertSame($atteso, Nominativo::capitalizza($dato));
    }
}
