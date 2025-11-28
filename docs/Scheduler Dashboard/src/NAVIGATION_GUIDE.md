# Staff Scheduling Dashboard - Navigation Guide

## Overview

The Staff Scheduling Dashboard is a comprehensive scheduling interface for managing care service assignments across SPO and SSPO staff.

## Entry Points & Deep Linking

### 1. From Staff Directory
When viewing the Staff Directory, each staff member has a "Schedule" button. Clicking it navigates to:
```
/spo/scheduling?staff_id=123
```

This will:
- Filter the scheduling grid to show only that staff member
- Highlight their row in the grid
- Display a filter pill that can be cleared

### 2. From Patient Care Plan
From a patient's Care Plan view, clicking "Open in Scheduler" or "View Scheduled Services" navigates to:
```
/spo/scheduling?patient_id=XYZ
```

This will:
- Focus the "Unscheduled Required Care" panel on that patient
- Highlight or filter assignments for that patient in the grid
- Display a filter pill that can be cleared

### 3. From Command Center Metrics
From TFS or Missed Care Rate metrics, deep links can navigate to:
```
/spo/scheduling
```

## Key Features

### Unscheduled Care Panel (Left)
- Shows patients with unmet care requirements
- Displays remaining hours/visits needed per service type
- "Assign" buttons open the Assign Care Service modal pre-populated with patient context

### Scheduling Grid (Main Area)
- Week/Month view toggle
- 7-day grid showing staff availability and assignments
- Color-coded assignment blocks by service category
- Click empty cells to create new assignments
- Click assignment blocks to edit/delete

### Filters (Header)
- Organization: All / SPO / SSPO
- Role: Filter by discipline (RN, PSW, OT, PT, etc.)
- Employment Type: FT/PT/Casual/SSPO
- Status: Active, On Leave
- Date range navigation

## Workflow Examples

### Scheduling from Unscheduled Care
1. View patient in left panel with unmet requirements
2. Click "Assign" for a specific service
3. Modal opens with:
   - Patient pre-selected
   - Service type pre-selected
   - List of eligible staff based on role/availability
4. Select staff, date, time, and duration
5. Assignment appears in grid
6. Remaining required hours/visits decrement

### Scheduling from Grid
1. Click an empty cell in the grid
2. Modal opens with:
   - Staff pre-selected (from row)
   - Date/time pre-selected (from cell)
   - List of patients needing services
3. Select patient and service type
4. Assignment appears in grid

### Editing Assignments
1. Click an existing assignment block
2. Edit modal opens with ability to:
   - Change assigned staff
   - Modify date/time
   - Adjust duration
   - Delete assignment
3. Changes reflect immediately in grid

## Technical Implementation

### Routes
- `/spo/scheduling` - SPO staff scheduling
- `/sspo/scheduling` - SSPO staff scheduling (mirrored view)

### Query Parameters
- `?staff_id=X` - Filter to specific staff member
- `?patient_id=Y` - Filter to specific patient

### API Endpoints
- `GET /api/v2/scheduling/requirements` - Unscheduled care data
- `GET /api/v2/scheduling/grid` - Staff availability and assignments
- `POST /api/v2/scheduling/assignments` - Create assignment
- `PATCH /api/v2/scheduling/assignments/:id` - Update assignment
- `DELETE /api/v2/scheduling/assignments/:id` - Delete assignment

## Metadata-Driven Architecture

All service types, roles, and business rules are driven by database metadata:
- `ServiceType` - Defines available care services
- `StaffRole` - Defines staff disciplines
- `RoleServiceMapping` - Eligibility rules
- `StaffAvailabilityBlock` - Availability constraints
- `CareBundleService` - Required weekly services per patient

No business logic is hard-coded in the UI components.
