# Staff Scheduling Dashboard - Quick Start Guide

## What You're Looking At

This is a **fully functional prototype** of the Staff Scheduling Dashboard for the Connected Capacity 2.1 system. It demonstrates:

✅ **Metadata-Driven Architecture** - All service types, roles, and eligibility rules come from data (not hard-coded)  
✅ **Deep Linking & Navigation** - URL-based filtering for staff/patient-centric views  
✅ **Unscheduled Care Tracking** - Real-time visibility into care requirements vs. scheduled services  
✅ **Interactive Scheduling Grid** - Week/month views with drag-and-drop-ready interface  
✅ **Capacity Management** - Visual indicators showing staff utilization  
✅ **Conflict Detection** - Warnings for over-scheduled or unavailable slots  

---

## Try It Out

### 1. View Unscheduled Care (Left Panel)

The left panel shows patients with unmet care requirements for the current week:

- **Johnathan Smith** (Ultra High RUG, with warning/danger flags)
  - Needs 2 Physical Therapy visits - click "Assign"
  - Needs 2 Skilled Nursing visits - click "Assign"

- **Eleanor Vance** (High RUG)
  - Has 2 more Occupational Therapy visits needed

- **Marcus Holloway** (Medium RUG)
  - Needs 1 Speech Therapy visit

Click any "Assign" button to open the assignment modal pre-populated with patient context.

---

### 2. Schedule from the Grid

The main grid shows 5 staff members across a 7-day week:

**To Create an Assignment:**
1. Click an **empty white cell** (staff member's available time)
2. Modal opens with staff and date pre-selected
3. Choose patient and service type
4. Set time and duration
5. Assignment appears as a colored block

**Color Legend:**
- Light Blue (#DBEAFE) = Rehab (PT, OT, ST)
- Light Red (#FEE2E2) = Skilled Nursing
- Light Pink (#FCE7F3) = Wound Care
- Light Yellow (#FEF3C7) = Medication Management
- Light Indigo (#E0E7FF) = PSW Care

**To Edit an Assignment:**
1. Click an existing colored block
2. Edit modal opens with options to:
   - Reassign to different staff
   - Change date/time
   - Adjust duration
   - Delete assignment

---

### 3. Test Deep Linking

Click the example links in the blue "Navigation Examples" box at the top:

**Staff-Centric View:**
- Filters grid to show only Sophia Rodriguez
- Highlights her row
- Shows filter pill with clear button

**Patient-Centric View:**
- Focuses unscheduled panel on Johnathan Smith
- Shows only his care requirements
- Highlights related assignments

**Full Dashboard:**
- Clears all filters
- Shows all staff and patients

---

### 4. Watch Real-Time Updates

**Schedule a Service:**
1. Click "Assign" next to "Johnathan Smith → Physical Therapy"
2. Select Ahmed Patel (PT) or David Lee (PTA)
3. Choose Tuesday at 10:00 AM, 1 hour duration
4. Click "Create Assignment"
5. **Watch:**
   - New blue block appears in grid
   - "2 of 2 visits" becomes "1 of 2 visits" in left panel
   - Ahmed's capacity bar increases

---

### 5. Explore Capacity Indicators

Each staff row shows a capacity bar:

- **Green** (<75%) = Comfortable capacity
- **Yellow** (75-90%) = Nearing capacity
- **Red** (>90%) = At/over capacity

**Current Utilization:**
- David Lee (PTA): 1h / 40h (2.5%)
- Sophia Rodriguez (OT): 1h / 40h (2.5%)
- Michael Chen (RN): 2.5h / 24h (10.4%)
- Jennifer Walsh (PSW): 4h / 40h (10%)
- Ahmed Patel (PT): 1h / 40h (2.5%)

---

## Architecture Highlights

### Metadata-Driven Design

All business logic is driven by metadata entities:

**ServiceType** (`mockServiceTypes`):
- Defines nursing, PSW, rehab, behaviour services
- Includes display names, categories, colors, and unit types

**StaffRole** (`staff.role`):
- RN, RPN, PSW, PT, OT, PTA, etc.
- Linked to service categories for eligibility

**RoleServiceMapping** (implicit in `AssignCareServiceModal`):
- Filters eligible staff based on role category matching service category

**StaffAvailabilityBlock** (`staff.availability`):
- Day-of-week based availability windows
- Grey cells = unavailable, white = available

**UnscheduledService** (in `UnscheduledPanel`):
- Derived from CareBundleService metadata
- Shows required vs. scheduled for each service type

### No Hard-Coded Business Rules

Notice that components **never** check specific service names or role titles:

❌ `if (serviceName === 'Physical Therapy')`  
✅ `if (serviceType.category === 'rehab')`

❌ `if (role === 'RN')`  
✅ `if (staff.role.category === serviceType.category)`

This allows new service types and roles to be added via database configuration without code changes.

---

## Integration Points (For Production)

### Backend API Endpoints

This prototype uses mock data. In production, wire to:

```typescript
GET /api/v2/scheduling/requirements
  → Returns UnscheduledCareItem[]
  → Powered by CareBundleAssignmentPlanner service

GET /api/v2/scheduling/grid
  → Returns StaffMember[] with availability + Assignment[]
  → Powered by SchedulingEngine service

POST /api/v2/scheduling/assignments
  → Creates ServiceAssignment
  → Validates via SchedulingEngine (capacity, availability, eligibility)

PATCH /api/v2/scheduling/assignments/:id
  → Updates assignment
  → Re-validates scheduling rules

DELETE /api/v2/scheduling/assignments/:id
  → Soft-deletes assignment
  → Updates unscheduled care counts
```

### Navigation Entry Points

**From Staff Directory:**
```tsx
<button onClick={() => navigate(`/spo/scheduling?staff_id=${staff.id}`)}>
  Schedule
</button>
```

**From Patient Care Plan:**
```tsx
<a href={`/spo/scheduling?patient_id=${patient.id}`}>
  View Scheduled Services
</a>
```

**From Command Center:**
```tsx
<MetricCard 
  title="Time to First Service"
  linkTo="/spo/scheduling"
/>
```

---

## What This Demonstrates

✅ **Complete scheduling workflow** from unscheduled care → assignment → capacity tracking  
✅ **Deep linking** for staff-centric and patient-centric views  
✅ **Metadata-driven eligibility** (no hard-coded service/role checks)  
✅ **Real-time capacity tracking** with visual indicators  
✅ **Conflict detection** (see Tuesday SN Visit with red error flag)  
✅ **Responsive grid layout** ready for production styling  
✅ **Modal-based assignment creation/editing** with validation  

---

## Next Steps for Production

1. **Replace mock data** with API calls to Laravel backend
2. **Add real-time updates** via WebSockets or polling
3. **Implement drag-and-drop** for quick rescheduling
4. **Add bulk operations** (copy week, auto-schedule, etc.)
5. **Integrate with Staff Directory** and Patient Care Plan views
6. **Add notification system** for conflicts and capacity warnings
7. **Implement SSPO view** at `/sspo/scheduling` (same components, different org filter)

---

## Test Scenarios

### Scenario 1: Schedule High-Priority Patient
1. Johnathan Smith (Ultra High RUG) has danger flags
2. Click "Assign" for Physical Therapy
3. Ahmed Patel (PT) available Mon-Fri 8-4
4. Schedule Tuesday 2:00 PM for 1 hour
5. Remaining visits: 2 → 1

### Scenario 2: Detect Capacity Constraint
1. Schedule multiple PSW Care assignments for Jennifer Walsh
2. Watch capacity bar turn yellow → red
3. Attempt to schedule beyond 40h/week
4. System should warn/block (add validation in production)

### Scenario 3: Handle Scheduling Conflict
1. Note David Lee has conflict on Tuesday 10:30 AM (red error flag)
2. Click the SN Visit block
3. Reassign to Michael Chen or change time
4. Conflict resolves

### Scenario 4: Staff-Centric Workflow
1. Click "View Sophia Rodriguez's schedule" demo link
2. Grid filters to only her row
3. Click Wednesday empty cell
4. Assign Eleanor Vance OT visit
5. Third OT visit completes Eleanor's requirements

---

**Questions?** See `NAVIGATION_GUIDE.md` for detailed routing and `TEST_DATA.md` for mock data reference.
