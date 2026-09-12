<?php

namespace Modules\Hr\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class HrServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Hr';

    protected string $nameLower = 'hr';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    // ApprovalService "manager" ve "department_manager" resolver'ları
    // AppServiceProvider::boot() içinde kaydedilir. HrServiceProvider
    // parent::boot()'u override ettiğinde nWidart modül yükleme sırasını
    // bozuyor ve testlerde PHP segfault'una yol açtığı için burada
    // özel bir boot davranışı tanımlanmıyor.
}
