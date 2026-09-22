<?php

namespace App\Models;

use App\Enums\JobName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationRun extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_name',
        'run_date',
        'status',
        'contractor_count',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'job_name' => JobName::class,
        'run_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all notifications created in this run.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'run_id');
    }

    /**
     * Check if this run has already completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark run as completed with contractor count.
     */
    public function markCompleted(int $count): bool
    {
        return $this->update([
            'status' => 'completed',
            'contractor_count' => $count,
        ]);
    }

    /**
     * Mark run as failed with error message.
     */
    public function markFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
