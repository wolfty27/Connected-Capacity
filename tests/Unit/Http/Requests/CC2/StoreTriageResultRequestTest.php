<?php

namespace Tests\Unit\Http\Requests\CC2;

use App\Http\Requests\CC2\StoreTriageResultRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreTriageResultRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_boolean_like_values_are_normalized(): void
    {
        $request = $this->prepareRequest([
            'acuity_level' => 'low',
            'dementia_flag' => '1',
            'mh_flag' => 'false',
            'rpm_required' => 'true',
            'fall_risk' => 0,
            'behavioural_risk' => 'on',
        ]);

        $this->assertTrue($request->input('dementia_flag'));
        $this->assertFalse($request->input('mh_flag'));
        $this->assertTrue($request->input('rpm_required'));
        $this->assertFalse($request->input('fall_risk'));
        $this->assertTrue($request->input('behavioural_risk'));

        $this->assertTrue($this->makeValidator($request)->passes());
    }

    public function test_invalid_boolean_values_fail_validation(): void
    {
        $request = $this->prepareRequest([
            'acuity_level' => 'low',
            'dementia_flag' => 'maybe',
        ]);

        $validator = $this->makeValidator($request);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dementia_flag', $validator->errors()->messages());
    }

    public function test_notes_validation_allows_string_or_null(): void
    {
        $request = $this->prepareRequest([
            'acuity_level' => 'medium',
            'notes' => null,
        ]);

        $this->assertTrue($this->makeValidator($request)->passes());

        $request = $this->prepareRequest([
            'acuity_level' => 'medium',
            'notes' => 'Detailed note',
        ]);

        $this->assertTrue($this->makeValidator($request)->passes());

        $request = $this->prepareRequest([
            'acuity_level' => 'medium',
            'notes' => ['invalid'],
        ]);

        $this->assertTrue($this->makeValidator($request)->fails());
    }

    public function test_acuity_level_enum_validation(): void
    {
        $request = $this->prepareRequest([
            'acuity_level' => 'critical',
        ]);
        $this->assertTrue($this->makeValidator($request)->passes());

        $request = $this->prepareRequest([
            'acuity_level' => 'invalid',
        ]);
        $this->assertTrue($this->makeValidator($request)->fails());
    }

    private function prepareRequest(array $data): StoreTriageResultRequest
    {
        $request = new StoreTriageResultRequest();
        $request->merge($data);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        return $request;
    }

    private function makeValidator(StoreTriageResultRequest $request)
    {
        return Validator::make($request->all(), $request->rules());
    }
}
