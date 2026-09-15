<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\CardPayment;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\PosTerminal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\CardPaymentService;
use Modules\Inventory\Models\Partner;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class CardPaymentTest extends TenantTestCase
{
    private PosTerminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);

        $bank = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'bank')->firstOrFail();

        $this->terminal = PosTerminal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test POS',
            'bank_journal_id' => $bank->id,
            'commission_rates' => ['1' => '1.5', '3' => '2.5', '6' => '3.5'],
            'settlement_days' => 1,
            'is_active' => true,
        ]);
    }

    public function test_recording_computes_commission_and_net_correctly(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);

        $card = app(CardPaymentService::class)->record(
            $this->terminal,
            $customer->id,
            '1000.00',
            3,
            now()->toDateString(),
            $this->tenantAdmin,
        );

        // 3 taksitte %2.5 → komisyon 25, net 975
        $this->assertSame('2.500', (string) $card->commission_rate);
        $this->assertSame('25.0000', (string) $card->commission_amount);
        $this->assertSame('975.0000', (string) $card->net_amount);
        $this->assertSame(CardPayment::STATUS_PENDING, $card->status);
    }

    public function test_settlement_transitions_card_and_records_bank_entry(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $card = app(CardPaymentService::class)->record(
            $this->terminal, $customer->id, '500', 1, now()->toDateString(), $this->tenantAdmin
        );

        $result = app(CardPaymentService::class)->settle($card);

        $this->assertSame(CardPayment::STATUS_SETTLED, $result->status);
        $this->assertNotNull($result->settled_at);
    }

    public function test_settling_a_settled_card_fails(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $card = app(CardPaymentService::class)->record(
            $this->terminal, $customer->id, '100', 1, now()->toDateString(), $this->tenantAdmin
        );
        app(CardPaymentService::class)->settle($card);

        $this->expectException(HttpException::class);
        app(CardPaymentService::class)->settle($card->fresh());
    }

    public function test_unknown_installment_defaults_to_zero_commission(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);

        // Terminal has 1/3/6, we send 9 → no rate defined → commission 0
        $card = app(CardPaymentService::class)->record(
            $this->terminal, $customer->id, '200', 9, now()->toDateString(), $this->tenantAdmin
        );

        $this->assertSame('0.000', (string) $card->commission_rate);
        $this->assertSame('0.0000', (string) $card->commission_amount);
        $this->assertSame('200.0000', (string) $card->net_amount);
    }
}
