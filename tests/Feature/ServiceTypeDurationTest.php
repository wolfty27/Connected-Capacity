<?php

namespace Tests\Feature;

use App\Models\ServiceType;
use Database\Seeders\CoreDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test that service types have correct default durations.
 *
 * Ensures the seeder creates service types with realistic durations
 * as specified in the CC2.1 requirements.
 */
class ServiceTypeDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed service types
        $this->seed(CoreDataSeeder::class);
    }

    /** @test */
    public function nursing_service_has_correct_duration()
    {
        $nursing = ServiceType::where('code', 'NUR')->first();

        $this->assertNotNull($nursing, 'Nursing service type should exist');
        $this->assertEquals(45, $nursing->default_duration_minutes, 'Nursing should have 45 min duration');
        $this->assertEquals(60, $nursing->min_gap_between_visits_minutes, 'Nursing should have 60 min gap');
    }

    /** @test */
    public function psw_service_has_correct_duration()
    {
        $psw = ServiceType::where('code', 'PSW')->first();

        $this->assertNotNull($psw, 'PSW service type should exist');
        $this->assertEquals(60, $psw->default_duration_minutes, 'PSW should have 60 min duration');
        $this->assertEquals(120, $psw->min_gap_between_visits_minutes, 'PSW should have 120 min gap');
    }

    /** @test */
    public function pt_service_has_correct_duration()
    {
        $pt = ServiceType::where('code', 'PT')->first();

        $this->assertNotNull($pt, 'PT service type should exist');
        $this->assertEquals(45, $pt->default_duration_minutes, 'PT should have 45 min duration');
        $this->assertEquals(120, $pt->min_gap_between_visits_minutes, 'PT should have 120 min gap');
    }

    /** @test */
    public function ot_service_has_correct_duration()
    {
        $ot = ServiceType::where('code', 'OT')->first();

        $this->assertNotNull($ot, 'OT service type should exist');
        $this->assertEquals(45, $ot->default_duration_minutes, 'OT should have 45 min duration');
        $this->assertEquals(120, $ot->min_gap_between_visits_minutes, 'OT should have 120 min gap');
    }

    /** @test */
    public function homemaking_service_has_correct_duration()
    {
        $hmk = ServiceType::where('code', 'HMK')->first();

        $this->assertNotNull($hmk, 'Homemaking service type should exist');
        $this->assertEquals(90, $hmk->default_duration_minutes, 'Homemaking should have 90 min duration');
        $this->assertEquals(180, $hmk->min_gap_between_visits_minutes, 'Homemaking should have 180 min gap');
    }

    /** @test */
    public function behavioral_supports_has_correct_duration()
    {
        $beh = ServiceType::where('code', 'BEH')->first();

        $this->assertNotNull($beh, 'Behavioral Supports service type should exist');
        $this->assertEquals(60, $beh->default_duration_minutes, 'Behavioral Supports should have 60 min duration');
        $this->assertEquals(120, $beh->min_gap_between_visits_minutes, 'Behavioral Supports should have 120 min gap');
    }

    /** @test */
    public function meal_delivery_has_correct_duration()
    {
        $meal = ServiceType::where('code', 'MEAL')->first();

        $this->assertNotNull($meal, 'Meal Delivery service type should exist');
        $this->assertEquals(30, $meal->default_duration_minutes, 'Meal Delivery should have 30 min duration');
    }

    /** @test */
    public function rpm_service_has_correct_configuration()
    {
        $rpm = ServiceType::where('code', 'RPM')->first();

        $this->assertNotNull($rpm, 'RPM service type should exist');
        $this->assertEquals(60, $rpm->default_duration_minutes, 'RPM should have 60 min duration for each visit');
        $this->assertEquals('fixed_visits', $rpm->scheduling_mode, 'RPM should use fixed_visits scheduling mode');
        $this->assertEquals(2, $rpm->fixed_visits_per_plan, 'RPM should have exactly 2 visits per care plan');
        $this->assertIsArray($rpm->fixed_visit_labels, 'RPM should have visit labels');
        $this->assertCount(2, $rpm->fixed_visit_labels, 'RPM should have 2 visit labels');
        $this->assertEquals('Setup', $rpm->fixed_visit_labels[0], 'First RPM visit should be Setup');
        $this->assertEquals('Discharge', $rpm->fixed_visit_labels[1], 'Second RPM visit should be Discharge');
    }

    /** @test */
    public function all_service_types_have_valid_durations()
    {
        $serviceTypes = ServiceType::all();

        $this->assertGreaterThan(0, $serviceTypes->count(), 'Service types should be seeded');

        foreach ($serviceTypes as $serviceType) {
            $this->assertNotNull(
                $serviceType->default_duration_minutes,
                "Service type {$serviceType->code} should have a default duration"
            );
            $this->assertGreaterThan(
                0,
                $serviceType->default_duration_minutes,
                "Service type {$serviceType->code} should have a positive duration"
            );
        }
    }
}
