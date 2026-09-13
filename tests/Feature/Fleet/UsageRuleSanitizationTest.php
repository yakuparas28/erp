<?php

namespace Tests\Feature\Fleet;

use App\Models\User;
use Modules\Fleet\Models\VehicleUsageRule;
use Tests\TenantTestCase;

/**
 * Kullanım kuralları içeriği personel rezervasyon ekranında raw HTML olarak
 * render edildiğinden, write-time sanitizasyon güvenlik zorunluluğudur.
 * Kötü niyetli bir Fleet Manager `<script>` veya event handler enjekte
 * etmemelidir.
 */
class UsageRuleSanitizationTest extends TenantTestCase
{
    public function test_script_and_on_handler_are_stripped_on_save(): void
    {
        $fm = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $fm->assignRole('Fleet Manager');
        $this->actingAs($fm);

        $malicious = '<p>Kural 1</p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">tıkla</a>';
        $this->put(route('app.fleet.usage-rules.update'), ['icerik_html' => $malicious])
            ->assertRedirect();

        $rule = VehicleUsageRule::forTenant($this->tenant->id);
        $this->assertStringNotContainsString('<script', $rule->icerik_html);
        $this->assertStringNotContainsString('onerror', $rule->icerik_html);
        $this->assertStringNotContainsString('javascript:', $rule->icerik_html);
        $this->assertStringContainsString('Kural 1', $rule->icerik_html);
    }
}
