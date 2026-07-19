<?php

namespace Tests\Feature;

use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

class ModuleScaffoldTest extends TestCase
{
    public function test_all_four_erp_modules_are_enabled(): void
    {
        $enabled = array_keys(Module::allEnabled());

        sort($enabled);

        $this->assertSame(['accounting', 'inventory', 'purchase', 'sales'], $enabled);
    }

    public function test_dependent_modules_declare_inventory_requirement(): void
    {
        foreach (['Sales', 'Purchase', 'Accounting'] as $name) {
            $this->assertSame(
                ['Inventory'],
                Module::find($name)->get('requires', []),
                "{$name} modülü Inventory bağımlılığını beyan etmeli",
            );
        }
    }

    public function test_inventory_module_has_no_requirements(): void
    {
        $this->assertSame([], Module::find('Inventory')->get('requires', []));
    }
}
