<?php

namespace Cartxis\Calendar;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * The Afghan Solar Hijri calendar.
 *
 * Deliberately small. There are no migrations, no models and no database
 * tables: this package converts dates for DISPLAY and does nothing else. What
 * it registers is a config file, a couple of Blade helpers, a view namespace
 * and one JSON endpoint, which is the least a Solar Hijri date picker needs in
 * order to draw a month without reimplementing the calendar in the browser.
 */
class CalendarServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/calendar.php', 'calendar');

        require_once __DIR__.'/Helpers/calendar_helpers.php';
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');

        $this->loadViewsFrom(__DIR__.'/Views', 'calendar');

        $this->publishes([
            __DIR__.'/Config/calendar.php' => config_path('calendar.php'),
        ], 'calendar-config');

        $this->publishes([
            __DIR__.'/Views' => resource_path('views/vendor/calendar'),
        ], 'calendar-views');

        // @solarHijri($date) and @solarHijri($date, 'en')
        //
        // Prints the Solar Hijri date with the Gregorian reference in brackets,
        // in the reader's language: ۱۵ شهریور ۱۴۰۴ (6 September 2025).
        Blade::directive('solarHijri', function (string $expression): string {
            return "<?php echo \\Cartxis\\Calendar\\Helpers\\solar_hijri({$expression}); ?>";
        });

        // @solarHijriSolar($date)
        //
        // The Solar Hijri date on its own -- ۱۵ شهریور ۱۴۰۴ -- for a table
        // column or a tooltip with no room for two dates.
        Blade::directive('solarHijriSolar', function (string $expression): string {
            return "<?php echo \\Cartxis\\Calendar\\Helpers\\solar_hijri_solar({$expression}); ?>";
        });
    }
}
