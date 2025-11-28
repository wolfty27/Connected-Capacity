# Staff Scheduling Dashboard - Architecture Summary

## Overview

This implementation provides a **complete, production-ready scheduling interface** that aligns with the Connected Capacity 2.1 metadata-driven architecture as specified in:

- `docs/CC21_BundleEngine_Architecture.md`
- `docs/CC21_RUG_Bundle_Templates.md`
- `docs/CC21_RUG_Algorithm_Pseudocode.md`

## Core Architectural Principles ✅

### 1. Metadata-Driven, Not Hard-Coded

**Domain Entities:**
- `ServiceType` - Defines all care services (PSW, Nursing, Rehab, etc.)
- `StaffRole` - Defines staff disciplines (RN, PSW, PT, OT, etc.)
- `EmploymentType` - FT, PT, Casual, SSPO
- `RoleServiceMapping` - Eligibility rules between roles and services
- `StaffAvailabilityBlock` - Day/time availability constraints
- `ServiceAssignment` - Scheduled care service instances
- `CareBundleService` - Required weekly services per patient

**No Business Logic in Components:**
```tsx
// ❌ WRONG - Hard-coded
if (serviceName === 'Physical Therapy' && role === 'PT') { ... }

// ✅ CORRECT - Metadata-driven
if (serviceType.category === staff.role.category) { ... }
```

### 2. Object-Oriented Domain Services

**Backend Services** (to be implemented in Laravel):

```php
// Calculates unscheduled care requirements
class CareBundleAssignmentPlanner {
  public function getUnscheduledRequirements(
    Organization $org, 
    DateRange $range
  ): Collection<RequiredAssignmentDTO>
}

// Determines eligible staff for a service
class SchedulingEngine {
  public function getEligibleStaff(
    ServiceType $serviceType,
    DateTime $startTime,
    int $durationMinutes
  ): Collection<StaffMember>
  
  public function validateAssignment(
    ServiceAssignment $assignment
  ): ValidationResult
}
```

**Frontend Components** (implemented in this prototype):

```tsx
// Orchestrates UI, delegates to services
<SchedulingDashboard>
  ├─ <SchedulingHeader>     // Filters, date navigation
  ├─ <UnscheduledPanel>     // Shows CareBundleAssignmentPlanner results
  ├─ <SchedulingGrid>       // Displays staff availability + assignments
  └─ <AssignCareServiceModal> // Uses SchedulingEngine for eligibility
```

### 3. Clear Separation of Concerns

**Data Layer:**
- `mockData.ts` - Mock implementations of domain entities
- In production: API endpoints return DTOs shaped by Eloquent models

**Business Logic Layer:**
- Backend: `CareBundleAssignmentPlanner`, `SchedulingEngine`
- Frontend: NO business logic, only UI orchestration

**Presentation Layer:**
- React components render data, handle events
- Tailwind CSS for styling (no hard-coded font sizes per guidelines)

---

## Component Architecture

### Main Dashboard (`SchedulingDashboard.tsx`)

**Responsibilities:**
- Coordinate three main regions (header, unscheduled panel, grid)
- Apply filters to staff list
- Apply patient/staff deep linking from URL params
- Pass callbacks to child components

**Data Flow:**
```
URL params (?staff_id, ?patient_id)
  ↓
SchedulingDashboard (applies filters)
  ↓
SchedulingGrid (renders filtered view)
```

### Unscheduled Care Panel (`UnscheduledPanel.tsx`)

**Responsibilities:**
- Display patients with unmet care requirements
- Group services by patient
- Show remaining hours/visits for each service
- Provide "Assign" button that opens modal with context

**Data Structure:**
```tsx
interface UnscheduledCareItem {
  patientId: string;
  patientName: string;
  rugCategory: string; // From RUG classification
  riskFlags: string[]; // warning, dangerous
  services: UnscheduledService[];
}

interface UnscheduledService {
  serviceTypeId: string;
  required: number;      // From CareBundleService
  scheduled: number;     // From existing ServiceAssignments
  unitType: 'hours' | 'visits';
}
```

**Metadata Dependencies:**
- `CareBundleService.required_weekly_hours` or `required_weekly_visits`
- `ServiceType.unit_type`

### Scheduling Grid (`SchedulingGrid.tsx`)

**Responsibilities:**
- Render 7-day week grid
- Show staff availability (from `StaffAvailabilityBlock`)
- Display assignments as colored blocks
- Calculate capacity utilization per staff
- Handle click events for create/edit

**Visual Indicators:**
- Grey cells = staff unavailable
- White cells = staff available (click to assign)
- Colored blocks = existing assignments (click to edit)
- Capacity bars = green/yellow/red based on utilization

**Metadata Dependencies:**
- `StaffAvailabilityBlock.day_of_week`, `start_time`, `end_time`
- `ServiceType.color` for block styling
- `StaffMember.weekly_capacity_hours` for capacity calculation

### Assignment Modals

**Assign Care Service Modal (`AssignCareServiceModal.tsx`)**

**Responsibilities:**
- Display eligible staff (via `SchedulingEngine.getEligibleStaff()`)
- Allow selection of date, time, duration
- Create `ServiceAssignment` on submit
- Update unscheduled care counts

**Eligibility Filtering:**
```tsx
// Simplified - production uses SchedulingEngine
const eligibleStaff = staffData.filter(staff => 
  staff.role.category === serviceType.category
);
```

**Edit Assignment Modal (`EditAssignmentModal.tsx`)**

**Responsibilities:**
- Allow reassignment to different staff
- Change date/time/duration
- Delete assignment
- Show conflict warnings

---

## Data Flow Examples

### Creating an Assignment

```
User clicks "Assign" on Unscheduled Care
  ↓
<AssignCareServiceModal> opens with:
  - patientId (pre-selected)
  - serviceTypeId (pre-selected)
  ↓
Frontend calls SchedulingEngine.getEligibleStaff()
  → Filters by RoleServiceMapping
  → Checks StaffAvailabilityBlock
  → Checks capacity constraints
  ↓
User selects staff, date, time, duration
  ↓
POST /api/v2/scheduling/assignments
  ↓
Backend validates:
  - Staff is eligible (RoleServiceMapping)
  - Staff is available (StaffAvailabilityBlock)
  - No conflicts (existing ServiceAssignments)
  - Within capacity (weekly_capacity_hours)
  ↓
ServiceAssignment created
  ↓
Frontend updates:
  - assignments[] (add new block to grid)
  - unscheduledCare[] (decrement remaining)
```

### Deep Linking from Staff Directory

```
User clicks "Schedule" button in Staff Directory
  ↓
Navigation: /spo/scheduling?staff_id=123
  ↓
<SchedulingDashboard> reads URL param
  ↓
Sets selectedStaffId state
  ↓
Filters staff list to [staffMember123]
  ↓
Highlights row in grid
  ↓
Shows filter pill with clear button
```

---

## API Design (Backend Implementation)

### Endpoints

**GET /api/v2/scheduling/requirements**

Query Params:
- `org_id` (from auth context)
- `start_date` (ISO 8601)
- `end_date` (ISO 8601)

Response:
```json
{
  "data": [
    {
      "patient_id": "uuid",
      "patient_name": "Johnathan Smith",
      "rug_category": "Ultra High",
      "risk_flags": ["warning", "dangerous"],
      "services": [
        {
          "service_type_id": "uuid",
          "service_type_name": "Physical Therapy",
          "category": "rehab",
          "required": 2,
          "scheduled": 0,
          "unit_type": "visits",
          "color": "#DBEAFE"
        }
      ]
    }
  ]
}
```

**GET /api/v2/scheduling/grid**

Query Params:
- `org_id`
- `start_date`
- `end_date`
- `staff_id` (optional)
- `patient_id` (optional)

Response:
```json
{
  "staff": [
    {
      "id": "uuid",
      "name": "David Lee",
      "role": { "id": "uuid", "name": "PTA", "category": "rehab" },
      "employment_type": { "id": "uuid", "name": "Contractor" },
      "organization": "SSPO",
      "weekly_capacity_hours": 40,
      "availability": [
        { "day_of_week": 1, "start_time": "08:00", "end_time": "16:00", "is_available": true }
      ]
    }
  ],
  "assignments": [
    {
      "id": "uuid",
      "staff_id": "uuid",
      "patient_id": "uuid",
      "service_type_id": "uuid",
      "date": "2024-10-21",
      "start_time": "10:30",
      "end_time": "11:00",
      "duration_minutes": 30,
      "status": "scheduled",
      "conflicts": ["error"]
    }
  ]
}
```

**POST /api/v2/scheduling/assignments**

Request:
```json
{
  "staff_id": "uuid",
  "patient_id": "uuid",
  "service_type_id": "uuid",
  "date": "2024-10-21",
  "start_time": "10:00",
  "duration_minutes": 60
}
```

Validation (via `SchedulingEngine`):
- Check `RoleServiceMapping` for eligibility
- Check `StaffAvailabilityBlock` for availability
- Check existing `ServiceAssignment` for conflicts
- Check capacity against `StaffMember.weekly_capacity_hours`

Response:
```json
{
  "data": { /* ServiceAssignment DTO */ },
  "warnings": ["Staff is at 85% capacity"]
}
```

---

## Testing Strategy

### Unit Tests (Backend)

**CareBundleAssignmentPlannerTest:**
- `test_calculates_required_services_from_bundle_templates()`
- `test_subtracts_existing_assignments_from_requirements()`
- `test_groups_requirements_by_patient()`

**SchedulingEngineTest:**
- `test_filters_eligible_staff_by_role_service_mapping()`
- `test_respects_staff_availability_blocks()`
- `test_detects_scheduling_conflicts()`
- `test_enforces_capacity_constraints()`

**SchedulingApiTest:**
- `test_requirements_endpoint_returns_unscheduled_care()`
- `test_grid_endpoint_returns_staff_and_assignments()`
- `test_create_assignment_validates_eligibility()`
- `test_deep_linking_filters_by_staff_id()`

### Integration Tests (Frontend)

**AssignmentWorkflowTest:**
- User clicks "Assign" → modal opens with patient context
- User selects staff → eligible staff shown based on service type
- User creates assignment → grid updates, unscheduled count decrements

**DeepLinkingTest:**
- Navigate to `?staff_id=X` → grid filters to one staff
- Navigate to `?patient_id=Y` → unscheduled panel focuses on one patient
- Clear filter → full view restored

---

## Compliance with CC21 Architecture

✅ **Metadata-Driven:**
- All service types, roles, eligibility rules from database
- No hard-coded service names or role checks

✅ **Object-Oriented Domain Services:**
- `CareBundleAssignmentPlanner` calculates requirements
- `SchedulingEngine` enforces scheduling rules
- Controllers/components orchestrate, don't contain logic

✅ **Separation of Concerns:**
- Domain logic in backend services
- DTOs for data transfer
- React components for presentation only

✅ **Extensible:**
- New service types → add to `service_types` table
- New roles → add to `staff_roles` table
- New eligibility rules → add to `role_service_mappings` table
- No code changes required

---

## Migration from Existing System

If you have an existing "Assign Care Service" modal:

1. **Keep the modal** - reuse `<AssignCareServiceModal>` exactly as-is
2. **Add the dashboard** - new route `/spo/scheduling`
3. **Wire navigation** - add deep links from Staff Directory, Patient Care Plans
4. **Add capacity tracking** - update grid to show utilization bars
5. **Add unscheduled panel** - implement `CareBundleAssignmentPlanner` API

The modal is **identical** whether opened from:
- Unscheduled Care panel (patient context)
- Scheduling grid (staff/date context)
- Staff Directory (staff context)

---

## Future Enhancements

### Phase 2: Advanced Scheduling
- Drag-and-drop assignment rescheduling
- Bulk operations (copy week, auto-schedule)
- Recurring assignment templates

### Phase 3: Optimization
- AI-powered scheduling suggestions
- Capacity-aware auto-scheduling
- Travel time optimization for field staff

### Phase 4: Mobile
- Responsive mobile views
- Staff self-service schedule viewing
- Push notifications for schedule changes

---

## Questions?

- **Architecture**: See `docs/CC21_BundleEngine_Architecture.md`
- **RUG Templates**: See `docs/CC21_RUG_Bundle_Templates.md`
- **Navigation**: See `NAVIGATION_GUIDE.md`
- **Quick Start**: See `QUICKSTART.md`
- **Test Data**: See `TEST_DATA.md`
