<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\TenantModuleActivation;
use Database\Seeders\ModuleSeeder;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

class EnsureModuleActiveTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ModuleSeeder::class);

        // TenantTestCase varsayılan olarak tüm modülleri aktive eder;
        // bu test lisans-red senaryolarını doğruladığı için baştan sıfırlar.
        TenantModuleActivation::where('tenant_id', $this->tenant->id)->delete();

        Route::middleware(['api', 'auth:sanctum', 'module:accounting'])
            ->get('/api/_test/accounting-ping', fn () => response()->json(['pong' => true]));

        Route::middleware(['api', 'auth:sanctum', 'module:inventory'])
            ->get('/api/_test/inventory-ping', fn () => response()->json(['pong' => true]));
    }

    public function test_request_is_rejected_when_module_is_not_activated(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->getJson('/api/_test/accounting-ping')
            ->assertForbidden()
            ->assertJsonPath('message', 'Bu modül paketinizde aktif değil');
    }

    public function test_request_passes_when_module_is_active(): void
    {
        TenantModuleActivation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'module_id' => Module::where('key', 'accounting')->firstOrFail()->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->getJson('/api/_test/accounting-ping')
            ->assertOk();
    }

    public function test_core_module_always_passes_without_activation(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->getJson('/api/_test/inventory-ping')
            ->assertOk();
    }

    public function test_deactivated_module_is_rejected(): void
    {
        TenantModuleActivation::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'module_id' => Module::where('key', 'accounting')->firstOrFail()->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->getJson('/api/_test/accounting-ping')
            ->assertForbidden();
    }
}
