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
use App\Policies\EmployeePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\PartnerPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\SalesOrderPolicy;
use App\Services\Approval\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\CardPayment;
use Modules\Accounting\Models\CheckAndNote;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Models\PosTerminal;
use Modules\Expenses\Models\Expense;
use Modules\Expenses\Models\ExpenseCategory;
use Modules\Fleet\Models\FleetTaskSetting;
use Modules\Fleet\Models\MaintenanceRecord;
use Modules\Fleet\Models\Project;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCalendarBlock;
use Modules\Fleet\Models\VehicleUsageRule;
use Modules\Hr\Models\ConsumptionRule;
use Modules\Hr\Models\CriticalDate;
use Modules\Hr\Models\Department;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\Holiday;
use Modules\Hr\Models\LeaveBalance;
use Modules\Hr\Models\LeaveHourConfig;
use Modules\Hr\Models\LeaveRequest;
use Modules\Hr\Models\LeaveType;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
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
        $this->registerGates();
        $this->registerPolicies();
        ApprovalService::bootDefaultResolvers();

        // Non-production'da lazy loading her yerde exception atar — böylece
        // N+1 gizli kalmaz; test suite'inde de yakalanır. Production'da tolerans
        // gösterilir ki tek bir kaçak lazy load 500 hatası vermesin.
        Model::preventLazyLoading(! app()->isProduction());
    }

    /**
     * Model-based policy'ler — controller'daki `abort_unless($x->owner_id === auth()->id())`
     * gibi dağınık kuralları tek yerde toplar. Kullanım:
     *   $this->authorize('update', $expense);   // controller
     *
     *   @can('update', $expense)                // blade
     */
    private function registerPolicies(): void
    {
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Partner::class, PartnerPolicy::class);
    }

    /**
     * Fleet approval için tek adım Gate. Demodaki `approve-vehicle-request`
     * ile birebir; `Fleet Manager` rolüne ait kullanıcılar onay verir.
     */
    private function registerGates(): void
    {
        Gate::define('approve-vehicle-request', function ($user): bool {
            return method_exists($user, 'hasRole') && $user->hasRole('Fleet Manager');
        });
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
            'check_and_note' => CheckAndNote::class,
            'pos_terminal' => PosTerminal::class,
            'card_payment' => CardPayment::class,
            'bank_statement' => BankStatement::class,
            'bank_statement_line' => BankStatementLine::class,
            'approval_workflow' => ApprovalWorkflow::class,
            'approval_workflow_step' => ApprovalWorkflowStep::class,
            'approval' => Approval::class,
            'approval_action' => ApprovalAction::class,
            'department' => Department::class,
            'employee' => Employee::class,
            'leave_type' => LeaveType::class,
            'leave_balance' => LeaveBalance::class,
            'leave_request' => LeaveRequest::class,
            'leave_hour_config' => LeaveHourConfig::class,
            'holiday' => Holiday::class,
            'critical_date' => CriticalDate::class,
            'consumption_rule' => ConsumptionRule::class,
            'project' => Project::class,
            'vehicle' => Vehicle::class,
            'vehicle_reservation' => Reservation::class,
            'maintenance_record' => MaintenanceRecord::class,
            'vehicle_calendar_block' => VehicleCalendarBlock::class,
            'vehicle_usage_rule' => VehicleUsageRule::class,
            'fleet_task_setting' => FleetTaskSetting::class,
            'expense' => Expense::class,
            'expense_category' => ExpenseCategory::class,
        ]);
    }
}
