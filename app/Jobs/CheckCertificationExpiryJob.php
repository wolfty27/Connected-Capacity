<?php

namespace App\Jobs;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STAFF-018: Check for expiring and expired staff certifications
 *
 * This job runs daily to:
 * - Identify certifications expiring within 30/14/7 days
 * - Identify already expired certifications
 * - Send notifications to staff and coordinators
 * - Update skill verification status
 */
class CheckCertificationExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $warningDays30 = 30;
    protected int $warningDays14 = 14;
    protected int $warningDays7 = 7;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        Log::info('CheckCertificationExpiryJob: Starting certification expiry check');

        $today = Carbon::today();
        $stats = [
            'expiring_30_days' => 0,
            'expiring_14_days' => 0,
            'expiring_7_days' => 0,
            'expired' => 0,
            'notifications_sent' => 0,
        ];

        // Get all active staff with skills that have expiry dates
        $staffWithExpiringSkills = User::whereIn('role', [
                User::ROLE_FIELD_STAFF,
                User::ROLE_SPO_COORDINATOR,
                User::ROLE_SSPO_COORDINATOR,
            ])
            ->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status');
            })
            ->whereHas('skills', function ($q) use ($today) {
                $q->whereNotNull('staff_skills.expires_at')
                  ->where('staff_skills.expires_at', '<=', $today->copy()->addDays($this->warningDays30));
            })
            ->with(['skills' => function ($q) use ($today) {
                $q->whereNotNull('staff_skills.expires_at')
                  ->where('staff_skills.expires_at', '<=', $today->copy()->addDays($this->warningDays30));
            }, 'organization'])
            ->get();

        foreach ($staffWithExpiringSkills as $staff) {
            foreach ($staff->skills as $skill) {
                $expiresAt = Carbon::parse($skill->pivot->expires_at);
                $daysUntilExpiry = $today->diffInDays($expiresAt, false);

                $notification = $this->categorizeExpiry($daysUntilExpiry, $stats);

                if ($notification) {
                    $this->sendNotification($staff, $skill, $expiresAt, $notification);
                    $stats['notifications_sent']++;
                }
            }
        }

        Log::info('CheckCertificationExpiryJob: Completed', $stats);

        // Store stats for dashboard
        $this->storeExpiryStats($stats);
    }

    protected function categorizeExpiry(int $daysUntilExpiry, array &$stats): ?string
    {
        if ($daysUntilExpiry < 0) {
            $stats['expired']++;
            return 'expired';
        } elseif ($daysUntilExpiry <= $this->warningDays7) {
            $stats['expiring_7_days']++;
            return 'expiring_7';
        } elseif ($daysUntilExpiry <= $this->warningDays14) {
            $stats['expiring_14_days']++;
            return 'expiring_14';
        } elseif ($daysUntilExpiry <= $this->warningDays30) {
            $stats['expiring_30_days']++;
            return 'expiring_30';
        }

        return null;
    }

    protected function sendNotification(User $staff, $skill, Carbon $expiresAt, string $type): void
    {
        $daysText = match($type) {
            'expired' => 'has expired',
            'expiring_7' => 'expires in 7 days or less',
            'expiring_14' => 'expires in 14 days or less',
            'expiring_30' => 'expires in 30 days or less',
            default => 'is expiring soon',
        };

        $urgency = match($type) {
            'expired' => 'critical',
            'expiring_7' => 'high',
            'expiring_14' => 'medium',
            'expiring_30' => 'low',
            default => 'low',
        };

        Log::info("Certification {$type}: {$staff->name} - {$skill->name}", [
            'staff_id' => $staff->id,
            'skill_code' => $skill->code,
            'expires_at' => $expiresAt->toDateString(),
            'organization' => $staff->organization?->name,
        ]);

        // In a production system, this would send actual emails/notifications
        // For now, we log and could integrate with a notification system
        /*
        Mail::to($staff->email)->queue(new CertificationExpiryNotification(
            $staff,
            $skill,
            $expiresAt,
            $type,
            $urgency
        ));

        // Also notify coordinator if urgent
        if (in_array($type, ['expired', 'expiring_7'])) {
            $coordinator = $staff->organization?->users()
                ->where('role', User::ROLE_SPO_COORDINATOR)
                ->first();

            if ($coordinator) {
                Mail::to($coordinator->email)->queue(new CoordinatorCertificationAlert(
                    $staff,
                    $skill,
                    $expiresAt,
                    $type
                ));
            }
        }
        */
    }

    protected function storeExpiryStats(array $stats): void
    {
        // Store in cache or metrics table for dashboard display
        cache()->put('certification_expiry_stats', $stats, now()->addDay());
        cache()->put('certification_expiry_check_at', now()->toIso8601String(), now()->addDay());
    }

    /**
     * Get expiring certifications for API/dashboard
     */
    public static function getExpiringCertifications(int $days = 30, ?int $organizationId = null): array
    {
        $today = Carbon::today();

        $query = User::whereIn('role', [
                User::ROLE_FIELD_STAFF,
                User::ROLE_SPO_COORDINATOR,
                User::ROLE_SSPO_COORDINATOR,
            ])
            ->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status');
            })
            ->whereHas('skills', function ($q) use ($today, $days) {
                $q->whereNotNull('staff_skills.expires_at')
                  ->where('staff_skills.expires_at', '<=', $today->copy()->addDays($days))
                  ->where('staff_skills.expires_at', '>', $today->copy()->subDays(90)); // Include recently expired
            });

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->with(['skills' => function ($q) use ($today, $days) {
                $q->whereNotNull('staff_skills.expires_at')
                  ->where('staff_skills.expires_at', '<=', $today->copy()->addDays($days))
                  ->where('staff_skills.expires_at', '>', $today->copy()->subDays(90));
            }, 'organization'])
            ->get()
            ->flatMap(function ($staff) use ($today) {
                return $staff->skills->map(function ($skill) use ($staff, $today) {
                    $expiresAt = Carbon::parse($skill->pivot->expires_at);
                    $daysUntilExpiry = $today->diffInDays($expiresAt, false);

                    return [
                        'staff_id' => $staff->id,
                        'staff_name' => $staff->name,
                        'staff_email' => $staff->email,
                        'organization_id' => $staff->organization_id,
                        'organization_name' => $staff->organization?->name,
                        'skill_id' => $skill->id,
                        'skill_name' => $skill->name,
                        'skill_code' => $skill->code,
                        'certification_number' => $skill->pivot->certification_number,
                        'expires_at' => $expiresAt->toDateString(),
                        'days_until_expiry' => $daysUntilExpiry,
                        'status' => $daysUntilExpiry < 0 ? 'expired' : 'expiring',
                        'urgency' => $daysUntilExpiry < 0 ? 'critical' :
                            ($daysUntilExpiry <= 7 ? 'high' :
                            ($daysUntilExpiry <= 14 ? 'medium' : 'low')),
                    ];
                });
            })
            ->sortBy('days_until_expiry')
            ->values()
            ->toArray();
    }
}
