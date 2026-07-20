<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\ProductVariantAttributeValue;
use Modules\Inventory\Models\StockMove;

/**
 * Ürün silme — immutable ledger'ı korumak için stok hareketi görmüş
 * bir ürün ASLA silinmez (defter kaydı yetim kalmasın). Kit'in kendisi
 * silinirken kendi bileşen bağlantıları da kaldırılır; ürün başka bir
 * kit'in bileşeniyse silinemez (o kit'in tanımı bozulur).
 */
class ProductDeletionService
{
    public function delete(Product $product): void
    {
        abort_if(StockMove::where('product_id', $product->id)->exists(), 422, __('This product has stock movement history and cannot be deleted.'));
        abort_if(
            ProductKitComponent::where('component_product_id', $product->id)->exists(),
            422,
            __('This product is used as a component in another kit and cannot be deleted.'),
        );

        DB::transaction(function () use ($product): void {
            $product->kitComponents()->delete();
            $product->barcodes()->delete();
            $product->lots()->delete();
            $product->quants()->delete();
            ProductVariantAttributeValue::where('product_id', $product->id)->delete();
            $product->delete();
        });
    }

    /**
     * Şablonu ve TÜM varyantlarını birlikte siler. Herhangi bir varyantın
     * stok hareketi varsa işlemin tamamı reddedilir (kısmi silme yok).
     */
    public function deleteTemplate(ProductTemplate $template): void
    {
        $variants = $template->variants()->get();

        foreach ($variants as $variant) {
            abort_if(
                StockMove::where('product_id', $variant->id)->exists(),
                422,
                __('Some variants of this template have stock movement history; the template cannot be deleted.'),
            );
            abort_if(
                ProductKitComponent::where('component_product_id', $variant->id)->exists(),
                422,
                __('A variant of this template is used as a component in a kit; the template cannot be deleted.'),
            );
        }

        DB::transaction(function () use ($template, $variants): void {
            foreach ($variants as $variant) {
                $variant->barcodes()->delete();
                $variant->lots()->delete();
                $variant->quants()->delete();
                ProductVariantAttributeValue::where('product_id', $variant->id)->delete();
                $variant->delete();
            }

            $template->attributeLines()->delete();
            $template->delete();
        });
    }
}
