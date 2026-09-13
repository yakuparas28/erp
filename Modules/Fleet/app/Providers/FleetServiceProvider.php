<?php

namespace Modules\Fleet\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Fleet\Console\Commands\CheckVehicleMtv;
use Modules\Fleet\Console\Commands\SendVehicleCriticalWindowReminders;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FleetServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Fleet';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'fleet';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendVehicleCriticalWindowReminders::class,
        CheckVehicleMtv::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
