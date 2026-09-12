<?php

namespace Tests\Feature\Sales;

use Modules\Sales\Models\DeliveryCarrier;
use Tests\TenantTestCase;

class DeliveryCarrierTest extends TenantTestCase
{
    public function test_index_lists_carriers(): void
    {
        DeliveryCarrier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Aras Kargo']);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/sales/carriers')
            ->assertOk()
            ->assertSee('Aras Kargo');
    }

    public function test_carrier_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/sales/carriers', [
            'name' => 'Yurtiçi Kargo',
            'code' => 'YRT',
            'tracking_url_template' => 'https://kargotakip.example.com/{tracking_number}',
        ])->assertRedirect(route('app.sales.carriers.index'));

        $this->assertDatabaseHas('delivery_carriers', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Yurtiçi Kargo',
            'code' => 'YRT',
        ]);
    }

    public function test_name_must_be_unique_per_tenant(): void
    {
        DeliveryCarrier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'MNG']);

        $this->actingAs($this->tenantAdmin)->post('/app/sales/carriers', [
            'name' => 'MNG',
        ])->assertSessionHasErrors('name');
    }

    public function test_tracking_url_for_replaces_placeholder(): void
    {
        $carrier = DeliveryCarrier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tracking_url_template' => 'https://track.example/?n={tracking_number}',
        ]);

        $this->assertSame('https://track.example/?n=ABC+123', $carrier->trackingUrlFor('ABC 123'));
        $this->assertNull($carrier->trackingUrlFor(null));
        $this->assertNull($carrier->trackingUrlFor(''));
    }
}
