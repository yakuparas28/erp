<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;

/**
 * Banka ekstresi import + otomatik eşleştirme.
 *
 * CSV format (esnek, boşluk-tolere): date, description, debit, credit,
 * balance (opsiyonel), external_ref (opsiyonel).
 *
 * Tarih formatı: DD.MM.YYYY veya YYYY-MM-DD; sayısal alanlarda hem
 * "1.234,56" (TR) hem "1234.56" (EN) formatı desteklenir.
 *
 * Otomatik eşleştirme: aynı gün ± 3 gün + aynı tutar + aynı journal.
 * Eşleşme tekil değilse manuel eşleştirmeye bırakılır.
 */
class BankStatementImportService
{
    public function import(Journal $bankJournal, UploadedFile $file, User $uploader): BankStatement
    {
        abort_unless($bankJournal->type === 'bank', 422, __('Only bank journals can receive statements.'));
        abort_unless($bankJournal->tenant_id === $uploader->tenant_id, 403);

        $rows = $this->parse($file);
        abort_if($rows === [], 422, __('The uploaded file contains no parseable rows.'));

        $hash = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($bankJournal, $file, $uploader, $rows, $hash) {
            $existing = BankStatement::withoutGlobalScopes()
                ->where('tenant_id', $uploader->tenant_id)
                ->where('bank_journal_id', $bankJournal->id)
                ->where('file_hash', $hash)
                ->first();
            abort_if($existing !== null, 422, __('This statement file has already been imported.'));

            $dates = array_column($rows, 'transaction_date');
            $statement = new BankStatement([
                'bank_journal_id' => $bankJournal->id,
                'original_filename' => $file->getClientOriginalName(),
                'file_hash' => $hash,
                'period_start' => min($dates),
                'period_end' => max($dates),
                'opening_balance' => (string) ($rows[0]['balance'] ?? '0'),
                'closing_balance' => (string) (end($rows)['balance'] ?? '0'),
                'total_lines' => count($rows),
                'matched_lines' => 0,
                'uploaded_by' => $uploader->id,
            ]);
            $statement->tenant_id = $uploader->tenant_id;
            $statement->save();

            $matched = 0;
            foreach ($rows as $row) {
                $line = new BankStatementLine([
                    'bank_statement_id' => $statement->id,
                    'transaction_date' => $row['transaction_date'],
                    'description' => $row['description'],
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                    'running_balance' => $row['balance'],
                    'external_ref' => $row['ref'] ?? null,
                    'status' => BankStatementLine::STATUS_UNMATCHED,
                ]);
                $line->tenant_id = $uploader->tenant_id;
                $line->save();

                if ($this->autoMatch($line, $bankJournal)) {
                    $matched++;
                }
            }

            $statement->update(['matched_lines' => $matched]);

            return $statement->fresh();
        });
    }

    /**
     * Otomatik eşleştirme: aynı tutarlı payment ±3 gün.
     * Tekil match yoksa manuel akışa bırakır.
     */
    private function autoMatch(BankStatementLine $line, Journal $journal): bool
    {
        $amount = $line->amount();
        $date = $line->transaction_date;

        $candidates = Payment::withoutGlobalScopes()
            ->where('tenant_id', $line->tenant_id)
            ->where('journal_id', $journal->id)
            ->whereBetween('payment_date', [$date->copy()->subDays(3), $date->copy()->addDays(3)])
            ->where('amount', $amount)
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            return false;
        }

        $line->update([
            'matched_type' => 'payment',
            'matched_id' => $candidates->first()->id,
            'status' => BankStatementLine::STATUS_AUTO,
        ]);

        return true;
    }

    public function matchManually(BankStatementLine $line, string $matchedType, int $matchedId): void
    {
        abort_unless(in_array($matchedType, ['payment', 'check_and_note', 'card_payment', 'journal_entry'], true), 422, __('Invalid match type.'));

        $line->update([
            'matched_type' => $matchedType,
            'matched_id' => $matchedId,
            'status' => BankStatementLine::STATUS_MANUAL,
        ]);
        $this->recomputeStatement($line);
    }

    public function ignore(BankStatementLine $line): void
    {
        $line->update([
            'matched_type' => null,
            'matched_id' => null,
            'status' => BankStatementLine::STATUS_IGNORED,
        ]);
        $this->recomputeStatement($line);
    }

    public function unmatch(BankStatementLine $line): void
    {
        $line->update([
            'matched_type' => null,
            'matched_id' => null,
            'status' => BankStatementLine::STATUS_UNMATCHED,
        ]);
        $this->recomputeStatement($line);
    }

    private function recomputeStatement(BankStatementLine $line): void
    {
        $stmt = $line->statement()->first();
        if ($stmt === null) {
            return;
        }
        $matched = BankStatementLine::withoutGlobalScopes()
            ->where('bank_statement_id', $stmt->id)
            ->whereIn('status', [BankStatementLine::STATUS_AUTO, BankStatementLine::STATUS_MANUAL, BankStatementLine::STATUS_IGNORED])
            ->count();
        $stmt->update(['matched_lines' => $matched]);
    }

    /**
     * CSV / XLSX benzeri: ilk satır header, geri kalanlar satır.
     * Column matching: date, description, debit, credit, balance, ref
     * (kolon adları case-insensitive; Türkçe alternatifleri: tarih,
     * açıklama, borç, alacak, bakiye, referans).
     *
     * @return array<int, array{transaction_date:string, description:string, debit:string, credit:string, balance:?string, ref:?string}>
     */
    private function parse(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return [];
        }

        // Delimiter tahmini
        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delim = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delim) ?: [];
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $header);

        $map = [
            'date' => $this->findColumn($header, ['date', 'tarih', 'islem_tarihi', 'işlem tarihi']),
            'description' => $this->findColumn($header, ['description', 'açıklama', 'aciklama', 'islem']),
            'debit' => $this->findColumn($header, ['debit', 'borç', 'borc', 'gider']),
            'credit' => $this->findColumn($header, ['credit', 'alacak', 'gelir']),
            'balance' => $this->findColumn($header, ['balance', 'bakiye', 'kalan']),
            'ref' => $this->findColumn($header, ['ref', 'referans', 'islem_no', 'işlem no']),
        ];

        while (($cols = fgetcsv($handle, 0, $delim)) !== false) {
            if (! isset($cols[$map['date']]) || trim((string) $cols[$map['date']]) === '') {
                continue;
            }
            $rows[] = [
                'transaction_date' => $this->normalizeDate((string) $cols[$map['date']]),
                'description' => trim((string) ($cols[$map['description']] ?? '')),
                'debit' => $this->normalizeAmount((string) ($cols[$map['debit']] ?? '0')),
                'credit' => $this->normalizeAmount((string) ($cols[$map['credit']] ?? '0')),
                'balance' => $map['balance'] !== null ? $this->normalizeAmount((string) ($cols[$map['balance']] ?? '0')) : null,
                'ref' => $map['ref'] !== null ? trim((string) ($cols[$map['ref']] ?? '')) : null,
            ];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $aliases
     */
    private function findColumn(array $header, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $idx = array_search(mb_strtolower($alias), $header, true);
            if ($idx !== false) {
                return $idx;
            }
        }

        return null;
    }

    private function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        // DD.MM.YYYY veya DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[\.\/-](\d{1,2})[\.\/-](\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        // ISO
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
        }

        return $raw;
    }

    private function normalizeAmount(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '-') {
            return '0';
        }
        // TR biçimi "1.234,56" → "1234.56"
        if (str_contains($raw, ',') && substr_count($raw, ',') === 1 && (str_contains($raw, '.') || preg_match('/,\d{1,2}$/', $raw))) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }
        // Negatif işaret parantez içi ise
        if (preg_match('/^\(([\d.]+)\)$/', $raw, $m)) {
            $raw = '-'.$m[1];
        }

        return is_numeric($raw) ? $raw : '0';
    }
}
