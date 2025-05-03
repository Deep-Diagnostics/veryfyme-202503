<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\PanelSwitch\PanelSwitch;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PanelSwitch::configureUsing(function (PanelSwitch $panelSwitch) {
            $panelSwitch->slideOver();
            $panelSwitch->modalWidth('sm');
            $panelSwitch->iconSize(16);
            $panelSwitch->icons([
                'administrator' => 'eos-admin',
                'app' => 'carbon-app',
            ], $asImage = false);
            $panelSwitch
            ->canSwitchPanels(fn (): bool => auth()->user()?->can('switch_panels'));

        });

    }
}
