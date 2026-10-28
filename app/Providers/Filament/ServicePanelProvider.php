<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Accesso;
use App\Filament\Pages\Oggi;
use App\Filament\Pubblico\Registrazione;
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
use Illuminate\Support\Facades\Route;
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
            ->login(Accesso::class)
            ->brandName('Space Riders Service')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('auto')
            // Non in /icons/: su Apache quell'indirizzo è riservato alle icone del server.
            ->favicon(fn (): string => asset('app-icone/favicon-32.png'))
            ->colors([
                'primary' => self::verdePetrolio(),
                'success' => self::verdePetrolio(),
                'warning' => Color::hex('#B45309'),
                'danger' => Color::hex('#B42318'),
                'info' => Color::hex('#2563EB'),
                'gray' => self::grigiDecisi(),
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
            // La pagina pubblica dove i clienti si registrano da soli: senza accesso.
            ->routes(fn () => Route::get('registrazione', Registrazione::class)->name('registrazione'))
            // Consegna, rientro e nuovo cliente sono finestre, apribili da ogni pagina.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): View => view('filament.operazioni.pulsanti'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.operazioni.finestre'))
            // Installabile sul telefono: manifest, icone e service worker.
            // Non sulla registrazione: è dei clienti, non va installata come app del banco.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): ?View => self::perIlBanco('filament.pwa.head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): ?View => self::perIlBanco('filament.pwa.body'))
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

    private static function perIlBanco(string $vista): ?View
    {
        return request()->routeIs('filament.service.registrazione') ? null : view($vista);
    }

    /**
     * Lo Stone di Tailwind con i toni medi spostati di un passo verso lo scuro:
     * testi secondari, icone e segnaposto si leggono meglio. Gli sfondi chiari
     * (50–300) restano quelli, così il pannello resta bianco e leggero.
     */
    private static function grigiDecisi(): array
    {
        $stone = Color::Stone;

        return [
            50 => $stone[50],
            100 => $stone[100],
            200 => $stone[200],
            300 => $stone[300],
            400 => $stone[500],
            500 => $stone[600],
            600 => $stone[700],
            700 => $stone[800],
            800 => $stone[900],
            900 => $stone[950],
            950 => $stone[950],
        ];
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
