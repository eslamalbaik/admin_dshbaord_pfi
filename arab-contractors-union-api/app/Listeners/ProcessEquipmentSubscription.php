<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\EquipmentPackage;
use App\Models\ContractorEquipmentSubscription;

class ProcessEquipmentSubscription
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;

        if ($payment->type === 'equipment_subscription' && $payment->equipment_package_id) {
            $package = EquipmentPackage::find($payment->equipment_package_id);
            
            if ($package) {
                ContractorEquipmentSubscription::create([
                    'contractor_id'         => $payment->contractor_id,
                    'equipment_package_id'  => $package->id,
                    'payment_id'            => $payment->id,
                    'starts_at'             => now(),
                    'expires_at'            => now()->addDays($package->duration_days),
                ]);
            }
        }
    }
}
