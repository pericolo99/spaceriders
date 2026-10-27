// Lettore di codici a barre con la fotocamera del telefono.
//
// Uso nelle pagine Livewire:
//   <div x-data="scanner($wire, 'aggiungi')"> … <video x-ref="video"></video> … </div>
// Ogni codice letto chiama il metodo indicato del componente. Lo stesso codice
// non viene ripassato per due secondi: la fotocamera lo legge più volte di fila.
//
// I lettori laser da banco non passano di qui: scrivono nel campo del codice
// come una tastiera e premono Invio.
import { BrowserMultiFormatReader } from '@zxing/browser';

const PAUSA_STESSO_CODICE = 2000;

function scanner(wire, metodo) {
    return {
        attivo: false,
        errore: null,
        controlli: null,
        ultimo: { codice: null, quando: 0 },

        async avvia() {
            this.errore = null;

            if (!navigator.mediaDevices?.getUserMedia) {
                this.errore = 'Questo browser non dà accesso alla fotocamera. Serve una connessione https.';
                return;
            }

            this.attivo = true;
            await this.$nextTick();

            try {
                const lettore = new BrowserMultiFormatReader();
                this.controlli = await lettore.decodeFromConstraints(
                    { video: { facingMode: 'environment' } },
                    this.$refs.video,
                    (risultato) => risultato && this.letto(risultato.getText()),
                );
            } catch (e) {
                this.attivo = false;
                this.errore = e?.name === 'NotAllowedError'
                    ? 'Permesso per la fotocamera negato: abilitalo dalle impostazioni del browser.'
                    : 'Fotocamera non disponibile.';
            }
        },

        ferma() {
            this.controlli?.stop();
            this.controlli = null;
            this.attivo = false;
        },

        letto(codice) {
            const adesso = Date.now();
            if (codice === this.ultimo.codice && adesso - this.ultimo.quando < PAUSA_STESSO_CODICE) {
                return;
            }
            this.ultimo = { codice, quando: adesso };
            navigator.vibrate?.(60);
            wire.call(metodo, codice);
        },

        destroy() {
            this.ferma();
        },
    };
}

const registra = () => window.Alpine.data('scanner', scanner);

if (window.Alpine) {
    registra();
} else {
    document.addEventListener('alpine:init', registra);
}
