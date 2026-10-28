<?php

// Tutte le pagine sono del pannello Filament (App\Providers\Filament\ServicePanelProvider),
// montato sulla radice del sito.

use Illuminate\Support\Facades\Route;

// Il vecchio indirizzo della registrazione, stampato sui cartelli e nei QR code.
Route::permanentRedirect('public/registrazione', '/registrazione');
