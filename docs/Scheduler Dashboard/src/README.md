# Staff Scheduling Dashboard - Connected Capacity 2.1

## 🎯 What This Is

A **complete, production-ready prototype** of the Staff Scheduling Dashboard for the Connected Capacity 2.1 home care management system. This implements the metadata-driven, object-oriented architecture specified in the CC21 documentation.

## ✨ Key Features

- ✅ **Metadata-Driven Architecture** - All service types, roles, and business rules from data (no hard-coding)
- ✅ **Deep Linking & Navigation** - URL-based filtering for staff/patient-centric views
- ✅ **Unscheduled Care Tracking** - Real-time visibility into care requirements vs. scheduled services
- ✅ **Interactive Scheduling Grid** - Week/month views with capacity indicators
- ✅ **Smart Eligibility Filtering** - Only shows eligible staff based on role/service mappings
- ✅ **Conflict Detection** - Visual warnings for scheduling conflicts
- ✅ **Capacity Management** - Real-time staff utilization tracking with color-coded indicators

## 📁 Project Structure

```
/
├── App.tsx                          # Main application entry point
├── types/
│   └── index.ts                     # TypeScript type definitions (domain model)
├── data/
│   └── mockData.ts                  # Mock implementations of domain entities
├── components/
│   ├── SchedulingDashboard.tsx      # Main dashboard coordinator
│   ├── SchedulingHeader.tsx         # Filters, date navigation, view toggle
│   ├── SchedulingGrid.tsx           # 7-day week grid with staff rows
│   ├── UnscheduledPanel.tsx         # Left panel showing unmet care requirements
│   ├── AssignCareServiceModal.tsx   # Create new assignments
│   ├── EditAssignmentModal.tsx      # Edit/delete existing assignments
│   ├── SchedulingFooter.tsx         # Help text and legend
│   ├── NavigationDemo.tsx           # Deep linking examples (demo only)
│   └── ServiceLegend.tsx            # Service type color legend
└── docs/
    ├── QUICKSTART.md                # 5-minute getting started guide
    ├── ARCHITECTURE_SUMMARY.md      # Complete architectural overview
    ├── NAVIGATION_GUIDE.md          # Deep linking and navigation patterns
    ├── TEST_DATA.md                 # Mock data reference
    └── BACKEND_IMPLEMENTATION_CHECKLIST.md  # Laravel backend implementation guide
```

## 🚀 Quick Start

### Try the Demo

1. **View Unscheduled Care** (left panel)
   - See patients with unmet care requirements
   - Click "Assign" to schedule services

2. **Schedule from Grid** (main area)
   - Click empty white cells to create assignments
   - Click colored blocks to edit/delete assignments

3. **Test Deep Linking** (blue demo box at top)
   - Click "View Sophia Rodriguez's schedule" → filters to one staff
   - Click "View Johnathan Smith's care" → focuses on one patient
   - Click "View all schedules" → clears filters

4. **Watch Real-Time Updates**
   - Create an assignment → see it appear in grid
   - Capacity bars update automatically
   - Unscheduled counts decrement

### Read the Docs

- **New to the project?** → Start with `QUICKSTART.md`
- **Need architecture details?** → Read `ARCHITECTURE_SUMMARY.md`
- **Implementing backend?** → Follow `BACKEND_IMPLEMENTATION_CHECKLIST.md`
- **Testing navigation?** → See `NAVIGATION_GUIDE.md`

## 🏗️ Architecture Highlights

### Metadata-Driven Domain Model

All business logic is driven by database entities, not hard-coded rules:

**Core Entities:**
- `ServiceType` - Defines care services (PSW, Nursing, Rehab, etc.)
- `StaffRole` - Defines staff disciplines (RN, PSW, PT, OT, etc.)
- `RoleServiceMapping` - Eligibility rules (which roles can perform which services)
- `StaffAvailabilityBlock` - When staff are available to work
- `ServiceAssignment` - Scheduled care service instances
- `CareBundleService` - Required weekly services per patient (from RUG classification)

**No Hard-Coding:**
```tsx
// ❌ WRONG - Hard-coded business logic
if (serviceName === 'Physical Therapy' && role === 'PT') { ... }

// ✅ CORRECT - Metadata-driven
if (serviceType.category === staff.role.category) { ... }
```

This means new service types and roles can be added via database configuration without code changes.

### Domain Services (Object-Oriented)

Business logic lives in backend services, not UI components:

**CareBundleAssignmentPlanner:**
- Calculates unscheduled care requirements
- Subtracts existing assignments from required weekly services
- Returns patients with unmet needs

**SchedulingEngine:**
- Determines eligible staff for a service
- Validates assignments against rules (availability, capacity, conflicts)
- Enforces scheduling constraints

**Controllers/Components:**
- Orchestrate domain services
- Handle presentation logic only
- NO business rules

## 🔗 Deep Linking & Navigation

### From Staff Directory
```tsx
<button onClick={() => navigate(`/spo/scheduling?staff_id=${staff.id}`)}>
  Schedule
</button>
```
Result: Grid filters to show only that staff member.

### From Patient Care Plan
```tsx
<a href={`/spo/scheduling?patient_id=${patient.id}`}>
  View Scheduled Services
</a>
```
Result: Unscheduled panel focuses on that patient's needs.

### From Command Center Metrics
```tsx
<MetricCard title="Time to First Service" linkTo="/spo/scheduling" />
```
Result: Opens full dashboard for scheduling.

## 📊 Visual Indicators

### Service Type Colors
- **Light Blue** (#DBEAFE) - Rehab (PT, OT, ST)
- **Light Red** (#FEE2E2) - Skilled Nursing
- **Light Pink** (#FCE7F3) - Wound Care
- **Light Yellow** (#FEF3C7) - Medication Management
- **Light Indigo** (#E0E7FF) - PSW Care
- **Light Cyan** (#E0F2FE) - Behavioural Supports

### Capacity Indicators
- **Green** (<75%) - Comfortable capacity
- **Yellow** (75-90%) - Nearing capacity
- **Red** (>90%) - At/over capacity

### Availability
- **Grey cells** - Staff unavailable
- **White cells** - Staff available (click to assign)
- **Colored blocks** - Existing assignments (click to edit)

## 🧪 Test Data

### Staff Members (5)
1. **David Lee** - PTA, SSPO, 40h/week (has conflict on Tue)
2. **Sophia Rodriguez** - OT, SPO, 40h/week
3. **Michael Chen** - RN, SPO Part-time, 24h/week
4. **Jennifer Walsh** - PSW, SPO, 40h/week
5. **Ahmed Patel** - PT, SPO, 40h/week

### Patients with Unscheduled Care (3)
1. **Johnathan Smith** - Ultra High RUG, needs PT + Nursing
2. **Eleanor Vance** - High RUG, needs 2 more OT visits
3. **Marcus Holloway** - Medium RUG, needs Speech Therapy

### Existing Assignments (8)
- Spread across Monday-Thursday
- Various service types (PSW, Nursing, Rehab)
- One with scheduling conflict (David Lee, Tuesday 10:30 AM)

See `TEST_DATA.md` for complete reference.

## 🛠️ Technology Stack

**Frontend:**
- React 18 with TypeScript
- Tailwind CSS v4.0 (no hard-coded font styles)
- Lucide React (icons)
- URL-based routing (query params)

**Backend (to be implemented):**
- Laravel 10+
- Eloquent ORM
- RESTful API (see `BACKEND_IMPLEMENTATION_CHECKLIST.md`)
- Domain Services pattern

## 📋 Implementation Status

### ✅ Complete (Frontend)
- Scheduling dashboard UI
- Unscheduled care panel
- Interactive grid with week/month views
- Assignment creation modal
- Assignment editing modal
- Deep linking support
- Capacity tracking
- Conflict detection UI
- Responsive layout
- Navigation examples

### 🔄 To Do (Backend)
- Implement `CareBundleAssignmentPlanner` service
- Implement `SchedulingEngine` service
- Create API endpoints (requirements, grid, assignments)
- Add validation logic
- Write backend tests
- Deploy to staging

See `BACKEND_IMPLEMENTATION_CHECKLIST.md` for detailed implementation guide.

## 🎓 Learning Path

1. **Quick Tour** (5 min) → `QUICKSTART.md`
2. **Understand Architecture** (15 min) → `ARCHITECTURE_SUMMARY.md`
3. **Explore Mock Data** (5 min) → `TEST_DATA.md`
4. **Learn Navigation** (10 min) → `NAVIGATION_GUIDE.md`
5. **Implement Backend** (varies) → `BACKEND_IMPLEMENTATION_CHECKLIST.md`

## 🔐 Security & Authorization

### Role-Based Access
- SPO users → `/spo/scheduling` (SPO staff only)
- SSPO users → `/sspo/scheduling` (SSPO staff only)
- Admins → Both views

### Data Scoping
- All queries scoped to user's organization
- Staff can only view their own schedules (unless admin)
- Patients filtered by organization

### Validation
- Backend validates all assignment rules
- Frontend shows warnings, backend enforces constraints
- Never trust client-side eligibility checks

## 🚢 Deployment

### Prerequisites
- Laravel backend with CC21 domain entities
- Database seeded with ServiceTypes, StaffRoles, RoleServiceMappings
- Frontend build system (Vite)

### Steps
1. Deploy backend API endpoints
2. Update frontend API base URL
3. Remove `NavigationDemo` component (demo only)
4. Test deep linking from Staff Directory and Patient Care Plans
5. Monitor capacity calculations and conflict detection

### Performance Considerations
- Grid renders efficiently with virtual scrolling for 50+ staff
- API endpoints should paginate for large organizations
- Cache staff availability blocks (rarely change)
- Debounce filter changes to reduce API calls

## 🐛 Troubleshooting

**Grid not filtering by staff_id:**
- Check URL params are being read in `App.tsx` useEffect
- Verify `selectedStaffId` is passed to SchedulingDashboard

**Assignments not appearing:**
- Check date range matches `weekStartDate`
- Verify `getDateString()` format matches API expectations
- Ensure assignment status is not 'cancelled'

**Capacity calculation wrong:**
- Check `durationMinutes` is correct in assignment data
- Verify `weeklyCapacityHours` on StaffMember
- Ensure date range includes all assignments for the week

## 📞 Support

- **Architecture Questions**: See `docs/CC21_BundleEngine_Architecture.md`
- **RUG Templates**: See `docs/CC21_RUG_Bundle_Templates.md`
- **Backend Implementation**: See `BACKEND_IMPLEMENTATION_CHECKLIST.md`
- **Feature Requests**: Create GitHub issue

## 📄 License

Connected Capacity 2.1 - Internal Company Project

---

**Built with ❤️ following CC21 metadata-driven architecture principles**
