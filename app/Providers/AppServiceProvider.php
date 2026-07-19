<?php

namespace App\Providers;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

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
        $this->configureMorphMap();
    }

    /**
     * Polymorphic ilişkilerde tam class adı yerine kısa alias zorunludur
     * (PRD 4.1.2). Her faz kendi alias'larını bu haritaya ekler.
     */
    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'tenant' => Tenant::class,
            'user' => User::class,
            'super_admin' => SuperAdmin::class,
        ]);
    }
}
