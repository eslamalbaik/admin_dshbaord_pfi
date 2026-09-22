<?php

namespace App\Events;

use App\Models\Contractor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryReminder
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Contractor $contractor,
        public ?\DateTime $expiryDate = null
    ) {
    }
}
