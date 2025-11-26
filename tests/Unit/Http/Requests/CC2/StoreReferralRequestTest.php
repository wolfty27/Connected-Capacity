<?php

namespace Tests\Unit\Http\Requests\CC2;

use App\Http\Requests\CC2\StoreReferralRequest;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreReferralRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_json_string_is_decoded(): void
    {
        $patient = Patient::factory()->create();
        $request = $this->prepareRequest([
            'patient_id' => $patient->id,
            'metadata' => '{"foo":"bar"}',
        ]);

        $this->assertSame(['foo' => 'bar'], $request->input('metadata'));
        $this->assertTrue($this->makeValidator($request)->passes());
    }

    public function test_metadata_defaults_to_empty_array_when_missing(): void
    {
        $patient = Patient::factory()->create();
        $request = $this->prepareRequest([
            'patient_id' => $patient->id,
        ]);

        $this->assertSame([], $request->input('metadata'));
        $this->assertTrue($this->makeValidator($request)->passes());
    }

    public function test_invalid_metadata_string_becomes_empty_array(): void
    {
        $patient = Patient::factory()->create();
        $request = $this->prepareRequest([
            'patient_id' => $patient->id,
            'metadata' => 'not-json',
        ]);

        $this->assertSame([], $request->input('metadata'));
        $this->assertTrue($this->makeValidator($request)->passes());
    }

    public function test_array_metadata_is_preserved(): void
    {
        $patient = Patient::factory()->create();
        $payload = ['needs' => ['psw' => true]];
        $request = $this->prepareRequest([
            'patient_id' => $patient->id,
            'metadata' => $payload,
        ]);

        $this->assertSame($payload, $request->input('metadata'));
        $this->assertTrue($this->makeValidator($request)->passes());
    }

    private function prepareRequest(array $data): StoreReferralRequest
    {
        $request = new StoreReferralRequest();
        $request->merge($data);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        return $request;
    }

    private function makeValidator(StoreReferralRequest $request)
    {
        return Validator::make($request->all(), $request->rules());
    }
}
