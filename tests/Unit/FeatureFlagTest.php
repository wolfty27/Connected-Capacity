<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\FeatureFlag;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_casts_and_scope()
    {
        $flag = FeatureFlag::create([
            'key' => 'cc2.enabled',
            'scope' => 'global',
            'enabled' => true,
            'payload' => ['intake' => true],
        ]);

        $this->assertTrue($flag->enabled);
        $this->assertEquals(['intake' => true], $flag->payload);
        $this->assertEquals($flag->id, FeatureFlag::forKey('cc2.enabled')->first()->id);
    }
}
