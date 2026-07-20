<?php

namespace App\Providers;

use App\Models\LicensePackage;
use App\Models\MailSetting;
use App\Models\Module;
use App\Models\NotificationTemplate;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            'module' => Module::class,
            'license_package' => LicensePackage::class,
            'tenant_subscription' => TenantSubscription::class,
            'tenant_module_activation' => TenantModuleActivation::class,
            'mail_setting' => MailSetting::class,
            'notification_template' => NotificationTemplate::class,
            'role' => Role::class,
            'warehouse' => \Modules\Inventory\Models\Warehouse::class,
            'location' => \Modules\Inventory\Models\Location::class,
            'product' => \Modules\Inventory\Models\Product::class,
            'product_lot' => \Modules\Inventory\Models\ProductLot::class,
            'stock_move' => \Modules\Inventory\Models\StockMove::class,
            'stock_quant' => \Modules\Inventory\Models\StockQuant::class,
            'inventory_adjustment' => \Modules\Inventory\Models\InventoryAdjustment::class,
            'warehouse_transfer' => \Modules\Inventory\Models\WarehouseTransfer::class,
            'permission' => Permission::class,
        ]);
    }
}
