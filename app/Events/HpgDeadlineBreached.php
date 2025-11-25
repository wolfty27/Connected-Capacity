<?php

namespace App\Events;

use App\Models\TriageResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when HPG response deadline has been breached.
 *
 * Per OHaH RFS: 15-minute response SLA. This event fires when
 * a referral exceeds the deadline without response.
 */
class HpgDeadlineBreached
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TriageResult $triageResult;
    public int $minutesOverdue;

    public function __construct(TriageResult $triageResult, int $minutesOverdue)
    {
        $this->triageResult = $triageResult;
        $this->minutesOverdue = $minutesOverdue;
    }
}
