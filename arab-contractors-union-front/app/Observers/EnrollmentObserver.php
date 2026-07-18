<?php

namespace App\Observers;

use App\Models\Enrollment;

class EnrollmentObserver
{
    /**
     * Handle the Enrollment "created" event.
     */
    public function created(Enrollment $enrollment): void
    {
        \App\Models\SystemStatistic::first()->increment('total_enrollments');
    }

    /**
     * Handle the Enrollment "updated" event.
     */
    public function updated(Enrollment $enrollment): void
    {
        //
    }

    /**
     * Handle the Enrollment "deleted" event.
     */
    public function deleted(Enrollment $enrollment): void
    {
        //
    }

    /**
     * Handle the Enrollment "restored" event.
     */
    public function restored(Enrollment $enrollment): void
    {
        //
    }

    /**
     * Handle the Enrollment "force deleted" event.
     */
    public function forceDeleted(Enrollment $enrollment): void
    {
        //
    }
}
