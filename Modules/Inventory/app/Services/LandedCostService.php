<?php

namespace Modules\Inventory\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\LandedCost;
use Modules\Inventory\Models\LandedCostDistribution;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;

/**
 * Landed Costs (PRD 3.6): nakliye/gümrük gibi ek maliyetler, seçilen
 * split_method'a göre ilgili alım hareketlerine dağıtılır ve o hareketlerin
 * maliyet katmanı geriye dönük güncellenir. Yalnızca fifo/avco ürünlerde
 * uygulanabilir (standard → 409); onay yalnızca 'approve landed costs'
 * iznine sahip kullanıcıda (varsayılan Tenant Admin).
 */
class LandedCostService
{
    /**
     * @param  list<int>  $stockMoveIds
     */
    public function validate(LandedCost $landedCost, array $stockMoveIds, User $approver): void
    {
        abort_unless($landedCost->status === 'draft', 422, __('Only draft landed costs can be validated.'));
        abort_unless($approver->can('approve landed costs'), 403, __('You are not allowed to approve landed costs.'));

        $moves = StockMove::withoutGlobalScopes()
            ->where('tenant_id', $landedCost->tenant_id)
            ->whereIn('id', $stockMoveIds)
            ->with('product')
            ->get();

        foreach ($moves as $move) {
            abort_if(
                $move->product->cost_method === 'standard',
                409,
                __('Landed costs cannot be applied to standard-costed products.'),
            );
        }

        $totalAmount = (string) $landedCost->lines()->sum('amount');
        $basisByMove = $this->computeBasis($landedCost, $moves);
        $totalBasis = array_reduce($basisByMove, fn (string $carry, string $basis) => bcadd($carry, $basis, 4), '0.0000');

        abort_if(bccomp($totalBasis, '0', 4) <= 0, 422, __('The distribution basis for the selected moves is zero.'));

        DB::transaction(function () use ($landedCost, $moves, $totalAmount, $basisByMove, $totalBasis): void {
            foreach ($moves as $move) {
                $share = bcdiv(bcmul($totalAmount, $basisByMove[$move->id], 4), $totalBasis, 4);

                $distribution = new LandedCostDistribution([
                    'landed_cost_id' => $landedCost->id,
                    'stock_move_id' => $move->id,
                    'allocated_amount' => $share,
                ]);
                $distribution->tenant_id = $landedCost->tenant_id;
                $distribution->save();

                $layer = StockValuationLayer::withoutGlobalScopes()
                    ->where('tenant_id', $landedCost->tenant_id)
                    ->where('stock_move_id', $move->id)
                    ->first();

                if ($layer !== null && bccomp($layer->qty, '0', 4) > 0) {
                    $unitCostIncrease = bcdiv($share, $layer->qty, 4);

                    $layer->update([
                        'unit_cost' => bcadd($layer->unit_cost, $unitCostIncrease, 4),
                        'remaining_value' => bcadd($layer->remaining_value, $share, 4),
                    ]);
                }
            }

            $landedCost->update(['status' => 'validated']);
        });
    }

    /**
     * @param  Collection<int, StockMove>  $moves
     * @return array<int, string>
     */
    private function computeBasis(LandedCost $landedCost, Collection $moves): array
    {
        return $moves->mapWithKeys(function (StockMove $move) use ($landedCost) {
            $product = $move->product;

            $basis = match ($landedCost->split_method) {
                'by_quantity' => (string) $move->qty,
                'by_weight' => bcmul((string) $move->qty, (string) ($product->weight ?? '0'), 4),
                'by_volume' => bcmul((string) $move->qty, (string) ($product->volume ?? '0'), 4),
                'by_current_cost' => $this->currentCostBasis($landedCost, $move),
            };

            return [$move->id => $basis];
        })->all();
    }

    /**
     * Bir hareketin ilgili katmanının orijinal değeri (qty × unit_cost);
     * kısmen tüketilmiş olsa bile dağıtım tabanı olarak sabit tutulur.
     */
    private function currentCostBasis(LandedCost $landedCost, StockMove $move): string
    {
        $layer = StockValuationLayer::withoutGlobalScopes()
            ->where('tenant_id', $landedCost->tenant_id)
            ->where('stock_move_id', $move->id)
            ->first();

        if ($layer === null) {
            return '0.0000';
        }

        return bcmul($layer->qty, $layer->unit_cost, 4);
    }
}
