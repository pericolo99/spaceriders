<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Ogni messaggio di validazione di Laravel ha la sua versione italiana. */
class TraduzioniTest extends TestCase
{
    public function test_le_chiavi_italiane_coprono_quelle_inglesi(): void
    {
        $base = dirname(__DIR__, 2).'/lang';

        foreach (['validation', 'auth', 'passwords', 'pagination'] as $file) {
            $en = require "{$base}/en/{$file}.php";
            $it = require "{$base}/it/{$file}.php";

            $this->assertSame([], array_diff(array_keys($en), array_keys($it)), "lang/it/{$file}.php incompleto");

            foreach ($en as $chiave => $valore) {
                if (is_array($valore) && $chiave !== 'custom' && $chiave !== 'attributes') {
                    $this->assertSame([], array_diff(array_keys($valore), array_keys($it[$chiave])), "lang/it/{$file}.php: {$chiave} incompleto");
                }
            }
        }
    }
}
