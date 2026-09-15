<?php

namespace Tests\Feature\Accounting;

use Illuminate\Http\Testing\File;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\BankStatementImportService;
use Modules\Inventory\Models\Partner;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class BankStatementImportTest extends TenantTestCase
{
    private Journal $bank;

    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
        $this->bank = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'bank')->firstOrFail();
    }

    public function test_import_parses_turkish_style_csv_and_creates_lines(): void
    {
        $csv = <<<'CSV'
Tarih;Açıklama;Borç;Alacak;Bakiye
15.09.2026;EFT MUSTERI A;0;1.500,00;1.500,00
16.09.2026;HAVALE TEDARIKCI X;750,25;0;749,75
CSV;

        $file = File::createWithContent('vakif.csv', $csv);
        $stmt = app(BankStatementImportService::class)->import($this->bank, $file, $this->tenantAdmin);

        $this->assertInstanceOf(BankStatement::class, $stmt);
        $this->assertSame(2, $stmt->total_lines);
        $this->assertSame('vakif.csv', $stmt->original_filename);

        $lines = BankStatementLine::where('bank_statement_id', $stmt->id)->orderBy('id')->get();
        $this->assertSame('1500.0000', (string) $lines[0]->credit);
        $this->assertSame('750.2500', (string) $lines[1]->debit);
    }

    public function test_import_auto_matches_a_payment_with_same_amount_and_date(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);

        $payment = new Payment([
            'partner_id' => $customer->id,
            'journal_id' => $this->bank->id,
            'amount' => '1500.00',
            'payment_date' => '2026-09-15',
        ]);
        $payment->tenant_id = $this->tenant->id;
        $payment->save();

        $csv = "Tarih;Açıklama;Borç;Alacak\n15.09.2026;EFT MUSTERI;0;1500,00";
        $file = File::createWithContent('bank.csv', $csv);

        $stmt = app(BankStatementImportService::class)->import($this->bank, $file, $this->tenantAdmin);
        $this->assertSame(1, $stmt->matched_lines);

        $line = BankStatementLine::where('bank_statement_id', $stmt->id)->firstOrFail();
        $this->assertSame(BankStatementLine::STATUS_AUTO, $line->status);
        $this->assertSame('payment', $line->matched_type);
        $this->assertSame($payment->id, $line->matched_id);
    }

    public function test_importing_same_file_twice_is_refused(): void
    {
        $csv = "Tarih;Açıklama;Borç;Alacak\n01.01.2026;X;0;100";
        app(BankStatementImportService::class)->import(
            $this->bank, File::createWithContent('a.csv', $csv), $this->tenantAdmin
        );

        $this->expectException(HttpException::class);
        app(BankStatementImportService::class)->import(
            $this->bank, File::createWithContent('a.csv', $csv), $this->tenantAdmin
        );
    }

    public function test_manual_match_promotes_line_and_updates_statement_count(): void
    {
        $csv = "Tarih;Açıklama;Borç;Alacak\n01.01.2026;X;0;250";
        $stmt = app(BankStatementImportService::class)->import(
            $this->bank, File::createWithContent('b.csv', $csv), $this->tenantAdmin
        );
        $line = BankStatementLine::where('bank_statement_id', $stmt->id)->firstOrFail();
        $this->assertSame(BankStatementLine::STATUS_UNMATCHED, $line->status);

        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $payment = new Payment([
            'partner_id' => $customer->id, 'journal_id' => $this->bank->id,
            'amount' => '250', 'payment_date' => '2026-01-01',
        ]);
        $payment->tenant_id = $this->tenant->id;
        $payment->save();

        app(BankStatementImportService::class)->matchManually($line, 'payment', $payment->id);

        $this->assertSame(BankStatementLine::STATUS_MANUAL, $line->fresh()->status);
        $this->assertSame(1, $stmt->fresh()->matched_lines);
    }
}
