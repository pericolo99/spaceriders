<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Oggi;
use App\Filament\Resources\Utenti\UserResource;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * L'app è il pannello: banco, ufficio e telefono usano le stesse pagine.
 *
 * L'aspetto segue la versione B dei mockup (canvas «Kaukau – Nuova
 * interfaccia»): bianco e grigi caldi, DM Sans, un solo colore d'azione,
 * menu in alto invece della barra laterale.
 */
class ServicePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('service')
            ->path('')
            ->login()
            ->brandName('Space Riders Service')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('auto')
            ->favicon(fn (): string => asset('icons/favicon-32.png'))
            ->colors([
                'primary' => self::verdePetrolio(),
                'success' => self::verdePetrolio(),
                'warning' => Color::hex('#B45309'),
                'danger' => Color::hex('#B42318'),
                'info' => Color::hex('#2563EB'),
                'gray' => Color::Stone,
            ])
            ->font('DM Sans')
            ->defaultThemeMode(ThemeMode::Light)
            ->topNavigation()
            ->userMenuItems([
                'utenti' => Action::make('utenti')
                    ->label('Utenti')
                    ->icon('heroicon-o-key')
                    ->url(fn (): string => UserResource::getUrl()),
            ])
            ->maxContentWidth(Width::SevenExtraLarge)
            ->viteTheme('resources/css/filament/service/theme.css')
            ->pages([
                Oggi::class,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->widgets([])
            // Consegna, rientro e nuovo cliente sono finestre, apribili da ogni pagina.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): View => view('filament.operazioni.pulsanti'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.operazioni.finestre'))
            // Installabile sul telefono: manifest, icone e service worker.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.pwa.head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.pwa.body'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Il teal di Tailwind spostato di un tono: Filament colora i bottoni col
     * 600, e il verde petrolio dei mockup (#0F766E) è il 700 di quella scala.
     * Con la scala normale i bottoni verrebbero verde acqua chiaro.
     */
    private static function verdePetrolio(): array
    {
        $teal = Color::Teal;

        return [
            50 => $teal[50],
            100 => $teal[100],
            200 => $teal[200],
            300 => $teal[300],
            400 => $teal[500],
            500 => $teal[600],
            600 => $teal[700],
            700 => $teal[800],
            800 => $teal[900],
            900 => $teal[950],
            950 => $teal[950],
        ];
    }
}
