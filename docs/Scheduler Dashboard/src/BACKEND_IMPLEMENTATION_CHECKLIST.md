# Backend Implementation Checklist

This checklist guides the Laravel backend team through implementing the API endpoints needed to power the Staff Scheduling Dashboard.

---

## Prerequisites ✅

Ensure these domain entities exist (from CC21 architecture docs):

- [ ] `ServiceType` model with fields:
  - `id`, `name`, `display_name`, `category`, `unit_type`, `color`
  
- [ ] `StaffRole` model with fields:
  - `id`, `name`, `display_name`, `category`
  
- [ ] `StaffMember` model with fields:
  - `id`, `name`, `role_id`, `employment_type_id`, `organization_type`, `weekly_capacity_hours`, `status`
  
- [ ] `StaffAvailabilityBlock` model with fields:
  - `staff_member_id`, `day_of_week`, `start_time`, `end_time`, `is_available`
  
- [ ] `ServiceAssignment` model with fields:
  - `id`, `staff_member_id`, `patient_id`, `service_type_id`, `date`, `start_time`, `end_time`, `duration_minutes`, `status`
  
- [ ] `CareBundleService` model with fields:
  - `care_bundle_template_id`, `service_type_id`, `required_weekly_hours`, `required_weekly_visits`
  
- [ ] `RoleServiceMapping` model with fields:
  - `staff_role_id`, `service_type_id`, `is_eligible`

---

## Phase 1: Core Domain Services

### 1.1 CareBundleAssignmentPlanner Service

**File:** `app/Services/Scheduling/CareBundleAssignmentPlanner.php`

```php
class CareBundleAssignmentPlanner {
  public function getUnscheduledRequirements(
    Organization $org,
    Carbon $startDate,
    Carbon $endDate
  ): Collection
  {
    // TODO: Implement logic
    // 1. Get all active patients for org
    // 2. For each patient:
    //    - Get their CareBundleTemplate (via RUG classification)
    //    - Get required services from CareBundleService
    //    - Get existing ServiceAssignments in date range
    //    - Calculate remaining: required - scheduled
    // 3. Filter to patients with remaining > 0
    // 4. Return Collection<RequiredAssignmentDTO>
  }
}
```

**DTO:**
```php
class RequiredAssignmentDTO {
  public string $patientId;
  public string $patientName;
  public string $rugCategory;
  public array $riskFlags;
  public array $services; // Array<UnscheduledServiceDTO>
}

class UnscheduledServiceDTO {
  public string $serviceTypeId;
  public string $serviceTypeName;
  public string $category;
  public float $required;
  public float $scheduled;
  public string $unitType; // 'hours' | 'visits'
  public string $color;
}
```

**Tests:**
- [ ] `test_calculates_required_services_from_bundle_template()`
- [ ] `test_subtracts_existing_assignments()`
- [ ] `test_filters_to_unscheduled_only()`
- [ ] `test_respects_date_range()`

---

### 1.2 SchedulingEngine Service

**File:** `app/Services/Scheduling/SchedulingEngine.php`

```php
class SchedulingEngine {
  public function getEligibleStaff(
    ServiceType $serviceType,
    Carbon $dateTime,
    int $durationMinutes
  ): Collection
  {
    // TODO: Implement logic
    // 1. Get all active StaffMembers
    // 2. Filter by RoleServiceMapping (eligible for serviceType)
    // 3. Filter by StaffAvailabilityBlock (available at dateTime)
    // 4. Check capacity: sum(existing assignments) + duration <= weekly_capacity
    // 5. Return Collection<StaffMember>
  }
  
  public function validateAssignment(
    ServiceAssignment $assignment
  ): ValidationResult
  {
    // TODO: Implement validation
    // 1. Check RoleServiceMapping (staff role eligible for service)
    // 2. Check StaffAvailabilityBlock (staff available at time)
    // 3. Check for conflicts (overlapping assignments)
    // 4. Check capacity constraints
    // 5. Return ValidationResult with errors/warnings
  }
}
```

**DTO:**
```php
class ValidationResult {
  public bool $isValid;
  public array $errors;   // Blocking errors
  public array $warnings; // Non-blocking warnings
}
```

**Tests:**
- [ ] `test_filters_by_role_service_mapping()`
- [ ] `test_respects_availability_blocks()`
- [ ] `test_detects_scheduling_conflicts()`
- [ ] `test_enforces_capacity_constraints()`
- [ ] `test_validates_eligible_role()`

---

## Phase 2: API Endpoints

### 2.1 Scheduling Requirements Endpoint

**Route:**
```php
Route::get('/api/v2/scheduling/requirements', [SchedulingController::class, 'requirements']);
```

**Controller:**
```php
public function requirements(Request $request)
{
  $request->validate([
    'start_date' => 'required|date',
    'end_date' => 'required|date|after:start_date',
  ]);
  
  $org = $request->user()->organization;
  $startDate = Carbon::parse($request->start_date);
  $endDate = Carbon::parse($request->end_date);
  
  $planner = app(CareBundleAssignmentPlanner::class);
  $requirements = $planner->getUnscheduledRequirements($org, $startDate, $endDate);
  
  return response()->json(['data' => $requirements]);
}
```

**Tests:**
- [ ] `test_returns_unscheduled_requirements()`
- [ ] `test_requires_authentication()`
- [ ] `test_respects_org_context()`
- [ ] `test_validates_date_range()`

---

### 2.2 Scheduling Grid Endpoint

**Route:**
```php
Route::get('/api/v2/scheduling/grid', [SchedulingController::class, 'grid']);
```

**Controller:**
```php
public function grid(Request $request)
{
  $request->validate([
    'start_date' => 'required|date',
    'end_date' => 'required|date|after:start_date',
    'staff_id' => 'nullable|exists:staff_members,id',
    'patient_id' => 'nullable|exists:patients,id',
  ]);
  
  $org = $request->user()->organization;
  
  // Get staff with availability
  $staffQuery = StaffMember::where('organization_id', $org->id)
    ->with(['role', 'employmentType', 'availability'])
    ->where('status', 'active');
    
  if ($request->staff_id) {
    $staffQuery->where('id', $request->staff_id);
  }
  
  $staff = $staffQuery->get();
  
  // Get assignments
  $assignmentsQuery = ServiceAssignment::whereBetween('date', [$request->start_date, $request->end_date])
    ->with(['serviceType', 'patient'])
    ->where('status', '!=', 'cancelled');
    
  if ($request->staff_id) {
    $assignmentsQuery->where('staff_member_id', $request->staff_id);
  }
  
  if ($request->patient_id) {
    $assignmentsQuery->where('patient_id', $request->patient_id);
  }
  
  $assignments = $assignmentsQuery->get();
  
  return response()->json([
    'staff' => StaffMemberResource::collection($staff),
    'assignments' => ServiceAssignmentResource::collection($assignments),
  ]);
}
```

**Tests:**
- [ ] `test_returns_staff_and_assignments()`
- [ ] `test_filters_by_staff_id()`
- [ ] `test_filters_by_patient_id()`
- [ ] `test_respects_date_range()`

---

### 2.3 Create Assignment Endpoint

**Route:**
```php
Route::post('/api/v2/scheduling/assignments', [SchedulingController::class, 'createAssignment']);
```

**Controller:**
```php
public function createAssignment(Request $request)
{
  $request->validate([
    'staff_id' => 'required|exists:staff_members,id',
    'patient_id' => 'required|exists:patients,id',
    'service_type_id' => 'required|exists:service_types,id',
    'date' => 'required|date',
    'start_time' => 'required|date_format:H:i',
    'duration_minutes' => 'required|integer|min:15|max:480',
  ]);
  
  $staff = StaffMember::findOrFail($request->staff_id);
  $serviceType = ServiceType::findOrFail($request->service_type_id);
  
  $assignment = new ServiceAssignment([
    'staff_member_id' => $request->staff_id,
    'patient_id' => $request->patient_id,
    'service_type_id' => $request->service_type_id,
    'date' => $request->date,
    'start_time' => $request->start_time,
    'duration_minutes' => $request->duration_minutes,
    'status' => 'scheduled',
  ]);
  
  $assignment->end_time = $this->calculateEndTime($request->start_time, $request->duration_minutes);
  
  // Validate via SchedulingEngine
  $engine = app(SchedulingEngine::class);
  $validation = $engine->validateAssignment($assignment);
  
  if (!$validation->isValid) {
    return response()->json([
      'errors' => $validation->errors,
    ], 422);
  }
  
  $assignment->save();
  
  return response()->json([
    'data' => new ServiceAssignmentResource($assignment),
    'warnings' => $validation->warnings,
  ], 201);
}

private function calculateEndTime(string $startTime, int $durationMinutes): string
{
  $start = Carbon::createFromFormat('H:i', $startTime);
  return $start->addMinutes($durationMinutes)->format('H:i');
}
```

**Tests:**
- [ ] `test_creates_assignment_with_valid_data()`
- [ ] `test_rejects_ineligible_staff()`
- [ ] `test_rejects_unavailable_time()`
- [ ] `test_detects_conflicts()`
- [ ] `test_returns_warnings_for_capacity()`

---

### 2.4 Update Assignment Endpoint

**Route:**
```php
Route::patch('/api/v2/scheduling/assignments/{assignment}', [SchedulingController::class, 'updateAssignment']);
```

**Controller:**
```php
public function updateAssignment(Request $request, ServiceAssignment $assignment)
{
  $request->validate([
    'staff_id' => 'nullable|exists:staff_members,id',
    'date' => 'nullable|date',
    'start_time' => 'nullable|date_format:H:i',
    'duration_minutes' => 'nullable|integer|min:15|max:480',
  ]);
  
  if ($request->has('staff_id')) {
    $assignment->staff_member_id = $request->staff_id;
  }
  
  if ($request->has('date')) {
    $assignment->date = $request->date;
  }
  
  if ($request->has('start_time')) {
    $assignment->start_time = $request->start_time;
  }
  
  if ($request->has('duration_minutes')) {
    $assignment->duration_minutes = $request->duration_minutes;
    $assignment->end_time = $this->calculateEndTime(
      $assignment->start_time, 
      $request->duration_minutes
    );
  }
  
  // Re-validate
  $engine = app(SchedulingEngine::class);
  $validation = $engine->validateAssignment($assignment);
  
  if (!$validation->isValid) {
    return response()->json(['errors' => $validation->errors], 422);
  }
  
  $assignment->save();
  
  return response()->json([
    'data' => new ServiceAssignmentResource($assignment),
    'warnings' => $validation->warnings,
  ]);
}
```

**Tests:**
- [ ] `test_updates_assignment_fields()`
- [ ] `test_revalidates_on_update()`
- [ ] `test_prevents_unauthorized_updates()`

---

### 2.5 Delete Assignment Endpoint

**Route:**
```php
Route::delete('/api/v2/scheduling/assignments/{assignment}', [SchedulingController::class, 'deleteAssignment']);
```

**Controller:**
```php
public function deleteAssignment(ServiceAssignment $assignment)
{
  $assignment->status = 'cancelled';
  $assignment->save();
  
  return response()->json(null, 204);
}
```

**Tests:**
- [ ] `test_soft_deletes_assignment()`
- [ ] `test_prevents_unauthorized_deletion()`

---

## Phase 3: API Resources

### 3.1 StaffMemberResource

```php
class StaffMemberResource extends JsonResource
{
  public function toArray($request)
  {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'role' => [
        'id' => $this->role->id,
        'name' => $this->role->name,
        'display_name' => $this->role->display_name,
        'category' => $this->role->category,
      ],
      'employment_type' => [
        'id' => $this->employmentType->id,
        'name' => $this->employmentType->name,
        'display_name' => $this->employmentType->display_name,
      ],
      'organization' => $this->organization_type,
      'weekly_capacity_hours' => $this->weekly_capacity_hours,
      'status' => $this->status,
      'availability' => $this->availability->map(fn($block) => [
        'day_of_week' => $block->day_of_week,
        'start_time' => $block->start_time,
        'end_time' => $block->end_time,
        'is_available' => $block->is_available,
      ]),
    ];
  }
}
```

---

### 3.2 ServiceAssignmentResource

```php
class ServiceAssignmentResource extends JsonResource
{
  public function toArray($request)
  {
    return [
      'id' => $this->id,
      'staff_id' => $this->staff_member_id,
      'patient_id' => $this->patient_id,
      'patient_name' => $this->patient->name,
      'service_type_id' => $this->service_type_id,
      'service_type_name' => $this->serviceType->display_name,
      'category' => $this->serviceType->category,
      'color' => $this->serviceType->color,
      'date' => $this->date,
      'start_time' => $this->start_time,
      'end_time' => $this->end_time,
      'duration_minutes' => $this->duration_minutes,
      'status' => $this->status,
      'conflicts' => $this->detectConflicts(), // Add helper method
    ];
  }
}
```

---

## Phase 4: Frontend Integration

### 4.1 Update Frontend API Client

Replace mock data imports with API calls:

```tsx
// Before (mock):
import { mockUnscheduledCare } from './data/mockData';

// After (API):
const fetchUnscheduledCare = async (startDate: string, endDate: string) => {
  const response = await fetch(
    `/api/v2/scheduling/requirements?start_date=${startDate}&end_date=${endDate}`
  );
  return response.json();
};
```

### 4.2 Add React Query / SWR

```tsx
import useSWR from 'swr';

function SchedulingDashboard() {
  const { data, error } = useSWR(
    [`/api/v2/scheduling/grid`, startDate, endDate],
    ([url, start, end]) => 
      fetch(`${url}?start_date=${start}&end_date=${end}`).then(r => r.json())
  );
  
  // Use data.staff and data.assignments
}
```

---

## Phase 5: Testing

### 5.1 Feature Tests

- [ ] `test_complete_scheduling_workflow()`
  - Create patient with RUG category
  - Create care bundle template
  - Fetch unscheduled requirements (should show new patient)
  - Create assignment
  - Fetch requirements again (should decrement)

### 5.2 Integration Tests

- [ ] `test_deep_linking_from_staff_directory()`
  - Simulate navigation with `?staff_id=X`
  - Verify grid filters to that staff

- [ ] `test_capacity_constraint_enforcement()`
  - Schedule staff to 100% capacity
  - Attempt to schedule more
  - Verify error returned

---

## Phase 6: Documentation

- [ ] Update API documentation (OpenAPI/Swagger)
- [ ] Add inline PHPDoc comments
- [ ] Update CC21 architecture docs with scheduling endpoints
- [ ] Create Postman collection for testing

---

## Rollout Checklist

- [ ] All tests passing (PHPUnit, Feature, Integration)
- [ ] API endpoints deployed to staging
- [ ] Frontend connected to staging API
- [ ] QA testing of complete workflows
- [ ] Performance testing (load test with 100+ staff, 1000+ assignments)
- [ ] Security review (authorization, input validation)
- [ ] Production deployment
- [ ] Monitoring & alerting setup

---

## Questions?

Contact:
- Architecture: See `ARCHITECTURE_SUMMARY.md`
- Frontend: See `QUICKSTART.md`
- Navigation: See `NAVIGATION_GUIDE.md`
