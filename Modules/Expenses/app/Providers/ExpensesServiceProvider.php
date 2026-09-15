<?php

namespace Modules\Expenses\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ExpensesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Expenses';

    protected string $nameLower = 'expenses';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
