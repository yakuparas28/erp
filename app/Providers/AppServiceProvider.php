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
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\RouteRule;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrderLine;
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
            'warehouse' => Warehouse::class,
            'location' => Location::class,
            'product' => Product::class,
            'product_lot' => ProductLot::class,
            'stock_move' => StockMove::class,
            'stock_quant' => StockQuant::class,
            'inventory_adjustment' => InventoryAdjustment::class,
            'warehouse_transfer' => WarehouseTransfer::class,
            'route_rule' => RouteRule::class,
            'purchase_order_line' => PurchaseOrderLine::class,
            'sales_order_line' => SalesOrderLine::class,
            'permission' => Permission::class,
        ]);
    }
}
