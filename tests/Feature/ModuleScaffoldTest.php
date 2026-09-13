<?php

namespace Tests\Feature;

use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

class ModuleScaffoldTest extends TestCase
{
    public function test_all_erp_modules_are_enabled(): void
    {
        $enabled = array_keys(Module::allEnabled());

        sort($enabled);

        $this->assertSame(['accounting', 'fleet', 'hr', 'inventory', 'purchase', 'sales'], $enabled);
    }

    public function test_dependent_modules_declare_inventory_requirement(): void
    {
        $expectedRequirements = [
            'Sales' => ['Inventory'],
            'Purchase' => ['Inventory'],
            'Accounting' => ['Inventory', 'Purchase', 'Sales'],
            'Fleet' => ['Hr'],
        ];

        foreach ($expectedRequirements as $name => $requires) {
            $this->assertSame(
                $requires,
                Module::find($name)->get('requires', []),
                "{$name} modülü {$requires[0]} bağımlılığını beyan etmeli",
            );
        }
    }

    public function test_inventory_module_has_no_requirements(): void
    {
        $this->assertSame([], Module::find('Inventory')->get('requires', []));
    }
}
