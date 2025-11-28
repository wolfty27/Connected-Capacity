<?php

namespace Database\Seeders;

use App\Models\ServiceRate;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * ServiceRatesSeeder - Seeds Ontario-aligned billing rates for service types.
 *
 * These are system-wide default rates (organization_id = null) based on
 * Ontario Health atHome's SPO billing rate structure.
 *
 * Organizations (SPO/SSPO) can override these rates through the admin UI.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class ServiceRatesSeeder extends Seeder
{
    /**
     * Ontario-aligned billing rate card (CAD).
     *
     * Based on typical Ontario Health atHome SPO contract rates.
     * Rates are stored in cents for precision.
     */
    private array $rateCard = [
        // Personal Support Services
        'PSW' => [
            'unit_type' => ServiceRate::UNIT_HOUR,
            'rate_cents' => 3500, // $35/hour
            'notes' => 'PSW personal support / IADL - hourly rate',
        ],
        'HMK' => [
            'unit_type' => ServiceRate::UNIT_HOUR,
            'rate_cents' => 3500, // $35/hour
            'notes' => 'Homemaking services - hourly rate',
        ],
        'RES' => [
            'unit_type' => ServiceRate::UNIT_HOUR,
            'rate_cents' => 3500, // $35/hour (daytime)
            'notes' => 'Respite care (daytime) - hourly rate',
        ],
        'DEL-ACTS' => [
            'unit_type' => ServiceRate::UNIT_HOUR,
            'rate_cents' => 4000, // $40/hour (delegated acts premium)
            'notes' => 'Delegated nursing acts by PSW - hourly rate with premium',
        ],

        // Nursing Services
        'NUR' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 11000, // $110/visit
            'notes' => 'Nursing (RN/RPN) visit - standard visit rate',
        ],
        'NP' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 20000, // $200/visit
            'notes' => 'Nurse Practitioner visit - advanced care visit rate',
        ],

        // Allied Health - Rehabilitation
        'PT' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 12000, // $120/visit
            'notes' => 'Physiotherapy visit',
        ],
        'OT' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 12000, // $120/visit
            'notes' => 'Occupational therapy visit',
        ],
        'SLP' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 13000, // $130/visit
            'notes' => 'Speech-language pathology visit',
        ],

        // Allied Health - Other
        'SW' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 12000, // $120/visit
            'notes' => 'Social work visit',
        ],
        'RD' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 11000, // $110/visit
            'notes' => 'Dietitian visit',
        ],
        'RT' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 13000, // $130/visit
            'notes' => 'Respiratory therapy visit',
        ],

        // Safety & Monitoring
        'PERS' => [
            'unit_type' => ServiceRate::UNIT_MONTH,
            'rate_cents' => 4500, // $45/month
            'notes' => 'Personal Emergency Response System (PERS/Lifeline) - monthly subscription',
        ],
        'RPM' => [
            'unit_type' => ServiceRate::UNIT_MONTH,
            'rate_cents' => 13000, // $130/month
            'notes' => 'Remote Patient Monitoring - monthly device lease + monitoring',
        ],
        'SEC' => [
            'unit_type' => ServiceRate::UNIT_CALL,
            'rate_cents' => 1500, // $15/call
            'notes' => 'Safety/wellness check (remote) - per call',
        ],

        // Logistics & Support
        'TRANS' => [
            'unit_type' => ServiceRate::UNIT_TRIP,
            'rate_cents' => 7000, // $70/trip
            'notes' => 'Medical transportation / escort - per trip',
        ],
        'MEAL' => [
            'unit_type' => ServiceRate::UNIT_SERVICE,
            'rate_cents' => 1200, // $12/meal
            'notes' => 'Meal delivery (Meals on Wheels) - per meal',
        ],
        'LAB' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 6000, // $60/visit
            'notes' => 'In-home laboratory visit fee',
        ],
        'PHAR' => [
            'unit_type' => ServiceRate::UNIT_SERVICE,
            'rate_cents' => 2500, // $25/service
            'notes' => 'Pharmacy support - delivery/service fee',
        ],
        'INTERP' => [
            'unit_type' => ServiceRate::UNIT_HOUR,
            'rate_cents' => 10000, // $100/hour
            'notes' => 'Language interpretation services - hourly rate',
        ],

        // Activation & Behavioral
        'REC' => [
            'unit_type' => ServiceRate::UNIT_HOUR,
            'rate_cents' => 3000, // $30/hour
            'notes' => 'Activation / recreation support - hourly rate',
        ],
        'BEH' => [
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 10000, // $100/visit
            'notes' => 'Caregiver coaching / education visit',
        ],
    ];

    /**
     * Additional service types that may need rates.
     * These are mapped to existing types or use default rates.
     */
    private array $additionalRates = [
        // Wound care nursing (specialized)
        'NUR_WOUND' => [
            'service_code' => 'NUR',
            'unit_type' => ServiceRate::UNIT_VISIT,
            'rate_cents' => 12500, // $125/visit (premium for wound care)
            'notes' => 'Wound care nursing visit - specialized rate',
        ],
        // Overnight respite
        'RES_OVERNIGHT' => [
            'service_code' => 'RES',
            'unit_type' => ServiceRate::UNIT_NIGHT,
            'rate_cents' => 26000, // $260/night (8h block)
            'notes' => 'Overnight respite (8-hour block) - per night rate',
        ],
    ];

    public function run(): void
    {
        $effectiveFrom = Carbon::today();

        $this->seedDefaultRates($effectiveFrom);

        $this->command->info('Ontario-aligned service rates seeded successfully.');
    }

    /**
     * Seed system-wide default rates (organization_id = null).
     */
    private function seedDefaultRates(Carbon $effectiveFrom): void
    {
        foreach ($this->rateCard as $serviceCode => $rateData) {
            $serviceType = ServiceType::where('code', $serviceCode)->first();

            if (!$serviceType) {
                $this->command->warn("ServiceType '{$serviceCode}' not found - skipping rate.");
                continue;
            }

            // Check if a system default rate already exists for this service
            $existingRate = ServiceRate::where('service_type_id', $serviceType->id)
                ->whereNull('organization_id')
                ->currentlyActive()
                ->first();

            if ($existingRate) {
                $this->command->info("Rate for '{$serviceCode}' already exists - skipping.");
                continue;
            }

            ServiceRate::create([
                'service_type_id' => $serviceType->id,
                'organization_id' => null, // System default
                'unit_type' => $rateData['unit_type'],
                'rate_cents' => $rateData['rate_cents'],
                'effective_from' => $effectiveFrom,
                'effective_to' => null, // Indefinitely active
                'notes' => $rateData['notes'],
                'created_by' => null, // System seeded
            ]);

            $rateDollars = $rateData['rate_cents'] / 100;
            $this->command->info("Created rate for '{$serviceCode}': \${$rateDollars} {$rateData['unit_type']}");
        }
    }
}
