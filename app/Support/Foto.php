<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Salva una foto scattata al banco: raddrizzata secondo l'EXIF (il telefono
 * spesso la scrive ruotata e annota solo l'orientamento) e ridotta a
 * LATO_MAX px, in JPEG. Una foto da 8 MB diventa qualche centinaio di KB.
 */
class Foto
{
    public const LATO_MAX = 1600;

    /** @return string il percorso sul disco public */
    public static function salva(UploadedFile $file, string $cartella): string
    {
        $percorso = $cartella.'/'.Str::uuid().'.jpg';
        $immagine = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        // Un formato che GD non legge (es. HEIC): si conserva il file così com'è.
        if ($immagine === false) {
            return $file->storeAs($cartella, Str::uuid().'.'.($file->guessExtension() ?: 'bin'), 'public');
        }

        $immagine = self::raddrizza($immagine, $file->getRealPath());
        $immagine = self::riduci($immagine);

        ob_start();
        imagejpeg($immagine, null, 82);
        Storage::disk('public')->put($percorso, (string) ob_get_clean());
        imagedestroy($immagine);

        return $percorso;
    }

    private static function raddrizza(\GdImage $immagine, string $file): \GdImage
    {
        $orientamento = function_exists('exif_read_data') ? (@exif_read_data($file)['Orientation'] ?? 1) : 1;

        return match ((int) $orientamento) {
            3 => imagerotate($immagine, 180, 0),
            6 => imagerotate($immagine, -90, 0),
            8 => imagerotate($immagine, 90, 0),
            default => $immagine,
        };
    }

    private static function riduci(\GdImage $immagine): \GdImage
    {
        [$l, $a] = [imagesx($immagine), imagesy($immagine)];
        $scala = self::LATO_MAX / max($l, $a);

        if ($scala >= 1) {
            return $immagine;
        }

        $ridotta = imagescale($immagine, (int) round($l * $scala), (int) round($a * $scala), IMG_BICUBIC);
        imagedestroy($immagine);

        return $ridotta;
    }
}
