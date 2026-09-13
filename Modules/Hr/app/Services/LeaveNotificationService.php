<?php

namespace Modules\Hr\Services;

use App\Mail\TemplatedMail;
use App\Models\Tenant;
use App\Services\Mail\NotificationTemplateService;
use App\Services\Mail\TenantMailer;
use Modules\Hr\Models\LeaveRequest;
use Throwable;

/**
 * İzin akışının dış-etki tarafı: submit / approve / reject / cancel
 * sonrası ilgili taraflara mail gönderir. LeaveRequestService ve
 * LeaveApprovalController buraya delege eder ki iş servisi salt CRUD +
 * kural olarak kalsın, notification tarafı bir yerde toplu yaşasın.
 *
 * SMTP yapılandırılmamış tenant'ta mail send'i sessizce loglanır; iş
 * akışı bloke olmaz.
 */
class LeaveNotificationService
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly TenantMailer $mailer,
    ) {}

    public function notifySubmitted(LeaveRequest $leave): void
    {
        $employee = $leave->employee()->with(['manager.user'])->first();
        $managerUser = $employee?->manager?->user;
        if ($managerUser === null) {
            return;
        }

        $this->send($leave->tenant_id, $managerUser->email, 'hr_leave_submitted', [
            'personel_adi' => $employee->full_name,
            'izin_turu' => $leave->leaveType?->name,
            'baslangic_tarihi' => $leave->start_date->format('d.m.Y'),
            'bitis_tarihi' => $leave->end_date->format('d.m.Y'),
            'gun_sayisi' => (string) $leave->total_days,
            'aciklama' => (string) ($leave->reason ?? '—'),
            'onay_baglantisi' => route('app.hr.leave-approvals.first'),
        ]);
    }

    public function notifyDecided(LeaveRequest $leave, string $decision, ?string $comment = null): void
    {
        $leave->refresh();
        $employee = $leave->employee()->with(['user'])->first();
        $employeeUser = $employee?->user;
        if ($employeeUser === null) {
            return;
        }

        $actorName = $leave->approval?->actions->first()?->actor?->name ?? '—';

        $key = $decision === 'approve' ? 'hr_leave_approved' : 'hr_leave_rejected';
        $this->send($leave->tenant_id, $employeeUser->email, $key, [
            'personel_adi' => $employee->full_name,
            'onaylayan_adi' => $actorName,
            'izin_turu' => $leave->leaveType?->name,
            'baslangic_tarihi' => $leave->start_date->format('d.m.Y'),
            'bitis_tarihi' => $leave->end_date->format('d.m.Y'),
            'gun_sayisi' => (string) $leave->total_days,
            'sebep' => (string) ($comment ?? '—'),
        ]);
    }

    public function notifyCancelled(LeaveRequest $leave): void
    {
        $employee = $leave->employee()->with(['manager.user'])->first();
        $managerUser = $employee?->manager?->user;
        if ($managerUser === null) {
            return;
        }

        $this->send($leave->tenant_id, $managerUser->email, 'hr_leave_cancelled', [
            'personel_adi' => $employee->full_name,
            'izin_turu' => $leave->leaveType?->name,
            'baslangic_tarihi' => $leave->start_date->format('d.m.Y'),
            'bitis_tarihi' => $leave->end_date->format('d.m.Y'),
        ]);
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    private function send(int $tenantId, string $to, string $key, array $vars): void
    {
        try {
            $rendered = $this->templates->render($key, $tenantId, array_merge([
                'firma_adi' => Tenant::find($tenantId)?->name ?? '',
                'uygulama_adi' => config('app.name'),
            ], $vars));
            $this->mailer->send($tenantId, $to, new TemplatedMail($rendered['subject'], $rendered['body']));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
