<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'journal_id' => Journal::factory(),
            'entry_date' => now()->toDateString(),
            'reference_type' => 'stock_move',
            'reference_id' => 1,
            'status' => 'draft',
        ];
    }
}
