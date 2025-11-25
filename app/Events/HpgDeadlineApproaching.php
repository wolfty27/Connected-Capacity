<?php

namespace App\Events;

use App\Models\TriageResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when HPG response deadline is approaching (10 minutes remaining).
 *
 * Per OHaH RFS: SPO must respond to HPG referrals within 15 minutes.
 * This event enables alerting/notification when deadline is at risk.
 */
class HpgDeadlineApproaching
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TriageResult $triageResult;
    public int $minutesRemaining;

    public function __construct(TriageResult $triageResult, int $minutesRemaining)
    {
        $this->triageResult = $triageResult;
        $this->minutesRemaining = $minutesRemaining;
    }
}
