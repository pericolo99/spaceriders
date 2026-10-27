<?php

/*
 * Solo ciò che cambia rispetto ai default di Livewire (vendor/livewire/livewire/config/livewire.php):
 * Laravel unisce le chiavi di primo livello, quindi il blocco qui sotto è completo.
 */
return [

    // Le foto del telefono pesano spesso 4-8 MB: il limite di Livewire (12 MB) si porta a 25,
    // lo stesso di upload_max_filesize in docker/php.ini.
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:25600'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
