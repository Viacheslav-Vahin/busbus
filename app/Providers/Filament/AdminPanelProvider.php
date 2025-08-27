<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('web')
            ->login()
            ->colors([
                'primary' => Color::Amber,
                'secondary' => Color::Gray,
                'background' => Color::Gray,
                'error' => Color::Red,
                'warning' => Color::Orange,
                'success' => Color::Green,
                'info' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
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
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => new HtmlString(<<<'HTML'
<script>
(function(){
  function initLivewireBridge(){
    const seatLayoutInput = document.querySelector('input#seat_layout');
    if (seatLayoutInput) {
      const observer = new MutationObserver(function(mutations){
        mutations.forEach(function(m){
          if (m.type === 'attributes' && m.attributeName === 'value') {
            const v = seatLayoutInput.value;
            if (v && v !== '[]') {
              if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('updateSeatLayout', [v]); // Livewire v3
              } else if (window.Livewire && typeof window.Livewire.emit === 'function') {
                window.Livewire.emit('updateSeatLayout', v);       // Livewire v2
              }
            }
          }
        });
      });
      observer.observe(seatLayoutInput, { attributes: true, attributeFilter: ['value'] });
    }

    window.addEventListener('seatSelected', function(e){
      const d = Array.isArray(e.detail) && e.detail.length ? e.detail[0] : e.detail;
      if (!d) return;
      const sel   = document.getElementById('selected_seat');
      const num   = document.getElementById('data.seat_number');
      const price = document.getElementById('data.price');
      if (sel)   { sel.value = d.seatNumber;  sel.dispatchEvent(new Event('change')); }
      if (num)   { num.value = d.seatNumber;  num.dispatchEvent(new Event('change')); }
      if (price) { price.value = d.seatPrice; price.dispatchEvent(new Event('change')); }
    });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initLivewireBridge();
  } else {
    document.addEventListener('DOMContentLoaded', initLivewireBridge, { once: true });
  }
})();
</script>
HTML)
            )
//            ->plugin(\TomatoPHP\FilamentTranslations\FilamentTranslationsPlugin::make())
//            ->plugin(\TomatoPHP\FilamentTranslations\FilamentTranslationsPlugin::make()->allowGPTScan())
//            ->plugin(\TomatoPHP\FilamentTranslations\FilamentTranslationsSwitcherPlugin::make())
            ->sidebarCollapsibleOnDesktop();

    }

}
