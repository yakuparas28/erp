<?php

namespace Tests\Feature\Hr;

use Modules\Hr\Models\ConsumptionRule;
use Tests\TenantTestCase;

class ConsumptionRuleTest extends TenantTestCase
{
    public function test_index_lists_rules(): void
    {
        ConsumptionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BR-IT-01',
            'category' => 'accrual',
            'name' => 'Yıllık İzin Hakediş',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('app.hr.consumption-rules.index'))
            ->assertOk()
            ->assertSee('BR-IT-01')
            ->assertSee('Yıllık İzin Hakediş');
    }

    public function test_can_create_rule(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.consumption-rules.store'), [
                'code' => 'BR-IT-13',
                'category' => 'consumption',
                'name' => 'Mazeret min süre',
                'legal_basis' => 'İş Kanunu 55',
                'is_active' => 1,
            ])
            ->assertRedirect(route('app.hr.consumption-rules.index'));

        $this->assertDatabaseHas('consumption_rules', ['code' => 'BR-IT-13']);
    }

    public function test_code_unique_per_tenant(): void
    {
        ConsumptionRule::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'BR-IT-01']);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.consumption-rules.store'), [
                'code' => 'BR-IT-01',
                'category' => 'consumption',
                'name' => 'Duplicate',
            ])
            ->assertSessionHasErrors(['code']);
    }
}
