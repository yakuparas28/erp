<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Inventory\Models\Partner;
use Tests\TenantTestCase;

class PaymentFlowScreensTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
    }

    public function test_receipts_screen_lists_only_customer_payments(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $cashJournal = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'cash')->firstOrFail();

        $customerPayment = $this->makePayment($customer->id, $cashJournal->id, '500');
        $supplierPayment = $this->makePayment($supplier->id, $cashJournal->id, '250');

        $response = $this->get(route('app.accounting.receipts.index'));
        $response->assertOk();
        $response->assertSee($customer->name);
        $response->assertDontSee($supplier->name);
    }

    public function test_disbursements_screen_lists_only_supplier_payments(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $cashJournal = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'cash')->firstOrFail();

        $this->makePayment($customer->id, $cashJournal->id, '500');
        $this->makePayment($supplier->id, $cashJournal->id, '250');

        $response = $this->get(route('app.accounting.disbursements.index'));
        $response->assertOk();
        $response->assertSee($supplier->name);
        $response->assertDontSee($customer->name);
    }

    public function test_all_payments_screen_lists_both(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $cashJournal = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'cash')->firstOrFail();

        $this->makePayment($customer->id, $cashJournal->id, '500');
        $this->makePayment($supplier->id, $cashJournal->id, '250');

        $response = $this->get(route('app.accounting.payments.index'));
        $response->assertOk();
        $response->assertSee($customer->name);
        $response->assertSee($supplier->name);
    }

    private function makePayment(int $partnerId, int $journalId, string $amount): Payment
    {
        $payment = new Payment([
            'partner_id' => $partnerId,
            'journal_id' => $journalId,
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
        ]);
        $payment->tenant_id = $this->tenant->id;
        $payment->save();

        return $payment;
    }
}
