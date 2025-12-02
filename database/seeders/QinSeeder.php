<?php

namespace Database\Seeders;

use App\Models\QinRecord;
use App\Models\ServiceProviderOrganization;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * QinSeeder - Seeds exactly one demo QIN for realistic dashboard demonstration.
 *
 * This creates a single officially issued QIN that:
 * - Matches a real RFP indicator (Referral Acceptance Rate)
 * - Has a realistic issued date within the demo week
 * - Has an upcoming QIP due date requiring action
 *
 * Per Ontario Health at Home RFP:
 * - QINs are issued when SPOs breach performance band thresholds
 * - Target is 0 Active QINs (compliance)
 * - SPOs must respond with a QIP within 7 days
 *
 * This QIN is based on the Referral Acceptance breach:
 * - DemoPatientsSeeder creates 15 patients (10 active + 5 in queue)
 * - 10 active patients: all accepted (is_accepted = true)
 * - 3 ready queue patients: all accepted (is_accepted = true)
 * - 2 not-ready queue patients: NOT accepted (is_accepted = false)
 * - Calculated rate: 13/15 = 86.7% → Band C (<95%)
 */
class QinSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding QIN records...');

        // Get the primary demo SPO organization (SEHC)
        $sehc = ServiceProviderOrganization::where('name', 'like', '%SEHC%')
            ->orWhere('name', 'like', '%SE Health%')
            ->first();

        if (!$sehc) {
            // Try to find any SPO organization
            $sehc = ServiceProviderOrganization::first();
        }

        if (!$sehc) {
            $this->command->warn('No SPO organization found. Skipping QIN seeding.');
            return;
        }

        // Clear existing seeded QINs (preserve any manually created ones)
        QinRecord::where('organization_id', $sehc->id)
            ->where('source', QinRecord::SOURCE_SEEDED)
            ->delete();

        // Create exactly ONE demo QIN
        // This represents an official QIN issued by OHaH for a Referral Acceptance breach
        // Based on seeded data: 13 of 15 referrals accepted (86.7%) - Band C breach
        $qin = QinRecord::create([
            'organization_id' => $sehc->id,
            'qin_number' => 'QIN-2025-001',
            'indicator' => QinRecord::INDICATORS['referral_acceptance'],
            'band_breach' => QinRecord::BAND_BREACHES['referral_acceptance']['C'],
            'metric_value' => 86.67, // 13/15 = 86.7%
            'evidence_period_start' => Carbon::now()->subDays(28),
            'evidence_period_end' => Carbon::now(),
            'issued_date' => Carbon::now()->subDays(3), // Issued 3 days ago
            'qip_due_date' => Carbon::now()->addDays(4), // Due in 4 days
            'status' => QinRecord::STATUS_OPEN,
            'ohah_contact' => 'Sarah Thompson',
            'notes' => 'Monthly compliance review: 13 of 15 referrals accepted (86.7%). 2 referrals pending acceptance beyond SLA. QIP submission required to address intake workflow delays.',
            'source' => QinRecord::SOURCE_SEEDED,
        ]);

        $this->command->info("Created demo QIN: {$qin->qin_number}");
        $this->command->info("  Indicator: {$qin->indicator}");
        $this->command->info("  Band Breach: {$qin->band_breach}");
        $this->command->info("  Metric Value: {$qin->metric_value}%");
        $this->command->info("  Evidence Period: {$qin->evidence_period_start->toDateString()} to {$qin->evidence_period_end->toDateString()}");
        $this->command->info("  Issued: {$qin->issued_date->toDateString()}");
        $this->command->info("  QIP Due: {$qin->qip_due_date->toDateString()}");
        $this->command->info("  Status: {$qin->status_label}");

        // Summary
        $activeCount = QinRecord::where('organization_id', $sehc->id)->active()->count();
        $this->command->info("\nQIN Summary for {$sehc->name}:");
        $this->command->info("  Active QINs: {$activeCount}");
        $this->command->info("  (Target: 0 - This is a demo to show the QIN workflow)");
    }
}
