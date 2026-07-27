<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\EInvoiceService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class EInvoiceServiceTest extends TenantTestCase
{
    private function service(): EInvoiceService
    {
        return app(EInvoiceService::class);
    }

    public function test_sending_a_draft_invoice_fails(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'draft']);

        try {
            $this->service()->send($invoice);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_sending_a_posted_invoice_marks_it_sent_and_stores_the_gib_uuid(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'posted']);

        $this->service()->send($invoice);

        $invoice->refresh();
        $this->assertSame('sent', $invoice->e_invoice_status);
        $this->assertNotEmpty($invoice->gib_uuid);
    }

    public function test_sending_an_already_sent_invoice_fails(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'posted',
            'e_invoice_status' => 'sent',
        ]);

        try {
            $this->service()->send($invoice);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_marking_a_sent_invoice_accepted_succeeds(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'posted',
            'e_invoice_status' => 'sent',
        ]);

        $this->service()->markAccepted($invoice);

        $this->assertSame('accepted', $invoice->fresh()->e_invoice_status);
    }

    public function test_marking_a_sent_invoice_rejected_succeeds(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'posted',
            'e_invoice_status' => 'sent',
        ]);

        $this->service()->markRejected($invoice);

        $this->assertSame('rejected', $invoice->fresh()->e_invoice_status);
    }

    public function test_marking_a_not_sent_invoice_accepted_fails(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'posted',
            'e_invoice_status' => 'not_sent',
        ]);

        try {
            $this->service()->markAccepted($invoice);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_marking_an_already_accepted_invoice_rejected_fails(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'posted',
            'e_invoice_status' => 'accepted',
        ]);

        try {
            $this->service()->markRejected($invoice);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
