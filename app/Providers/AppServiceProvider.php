<?php

namespace App\Providers;

use App\Models\Approval\Approval;
use App\Models\Approval\ApprovalAction;
use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\LicensePackage;
use App\Models\MailSetting;
use App\Models\Module;
use App\Models\NotificationTemplate;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;
use Modules\Hr\Models\Department;
use Modules\Hr\Models\Employee;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\RouteRule;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrder;
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
        $this->registerBladeDirectives();
        ApprovalService::bootDefaultResolvers();
    }

    /**
     * @module('sales') ... @endmodule — geçerli kullanıcının tenant'ında
     * modül aktifse render eder. Süper admin oturumunda tenant yoktur; o
     * durumda false döner (süper admin tenant panelini kullanmaz).
     */
    private function registerBladeDirectives(): void
    {
        Blade::if('module', function (string $key): bool {
            return auth()->user()?->tenant?->hasActiveModule($key) ?? false;
        });
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
            'purchase_order' => PurchaseOrder::class,
            'sales_order' => SalesOrder::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            'approval_workflow' => ApprovalWorkflow::class,
            'approval_workflow_step' => ApprovalWorkflowStep::class,
            'approval' => Approval::class,
            'approval_action' => ApprovalAction::class,
            'department' => Department::class,
            'employee' => Employee::class,
        ]);
    }
}
