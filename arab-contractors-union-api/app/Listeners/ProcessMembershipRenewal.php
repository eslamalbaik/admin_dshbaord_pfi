<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Services\MembershipRenewalService;

class ProcessMembershipRenewal
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private MembershipRenewalService $renewalService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentConfirmed $event): void
    {
        // خدمة تجديد العضوية تتكفل بالتأكد مما إذا كانت الدفعة تحتوي على رسوم عضوية
        $this->renewalService->renewFromPayment($event->payment, $event->authUserId);
    }
}
