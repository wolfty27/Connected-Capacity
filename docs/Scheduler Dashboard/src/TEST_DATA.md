# Test Data Documentation

## Staff Members

### David Lee
- **ID**: staff-1
- **Role**: PTA (Physical Therapy Assistant)
- **Employment**: Contractor
- **Organization**: SSPO
- **Capacity**: 40h/week
- **Availability**: Mon-Fri, 8:00-16:00

### Sophia Rodriguez
- **ID**: staff-2
- **Role**: OT (Occupational Therapist)
- **Employment**: Full-time
- **Organization**: SPO
- **Capacity**: 40h/week
- **Availability**: Mon-Fri, 9:00-17:00

### Michael Chen
- **ID**: staff-3
- **Role**: RN (Registered Nurse)
- **Employment**: Part-time
- **Organization**: SPO
- **Capacity**: 24h/week
- **Availability**: Mon-Thu, 8:00-14:00

### Jennifer Walsh
- **ID**: staff-4
- **Role**: PSW (Personal Support Worker)
- **Employment**: Full-time
- **Organization**: SPO
- **Capacity**: 40h/week
- **Availability**: Mon-Fri, 7:00-15:00

### Ahmed Patel
- **ID**: staff-5
- **Role**: PT (Physical Therapist)
- **Employment**: Full-time
- **Organization**: SPO
- **Capacity**: 40h/week
- **Availability**: Mon-Fri, 8:00-16:00

## Patients with Unscheduled Care

### Johnathan Smith
- **RUG Category**: Ultra High
- **Risk Flags**: Warning, Dangerous
- **Unscheduled Services**:
  - Physical Therapy: 2 visits required, 0 scheduled
  - Skilled Nursing: 2 visits required, 0 scheduled

### Eleanor Vance
- **RUG Category**: High
- **Unscheduled Services**:
  - Occupational Therapy: 3 visits required, 1 scheduled (2 remaining)

### Marcus Holloway
- **RUG Category**: Medium
- **Risk Flags**: Warning
- **Unscheduled Services**:
  - Speech Therapy: 1 visit required, 0 scheduled

## Service Types

### Nursing Category
- **Skilled Nursing** - #FEE2E2 (light red)
- **Wound Care** - #FCE7F3 (light pink)
- **Medication Management** - #FEF3C7 (light yellow)

### PSW Category
- **PSW Care** - #E0E7FF (light indigo)

### Rehab Category
- **Physical Therapy** - #DBEAFE (light blue)
- **Occupational Therapy** - #DBEAFE (light blue)
- **Speech Therapy** - #DBEAFE (light blue)

### Behaviour Category
- **Behavioural Supports** - #E0F2FE (light cyan)

## Existing Assignments

### Week View (Current Week)

**Monday**:
- Michael Chen: Medication Management, 8:00-9:30 (Patricia Wong)
- Jennifer Walsh: PSW Care, 9:00-11:00 (Betty White)

**Tuesday**:
- David Lee: SN Visit, 10:30-11:00 (Robert Chen) ⚠️ Has conflict
- Jennifer Walsh: PSW Care, 10:00-12:00 (George Martin)
- Ahmed Patel: PT Session, 14:00-15:00 (Susan Clark)

**Wednesday**:
- David Lee: Wound Care, 10:15-10:45 (Maria Garcia)
- Sophia Rodriguez: OT Service, 15:00-16:00 (Eleanor Vance)

**Thursday**:
- Michael Chen: PT Eval, 13:00-14:00 (James Peterson)

## Testing Workflows

### 1. Schedule from Unscheduled Care
1. Click "Assign" next to "Johnathan Smith → Physical Therapy"
2. Modal opens with patient/service pre-selected
3. Eligible staff shown: Ahmed Patel (PT), David Lee (PTA)
4. Select staff, date, time
5. Assignment appears in grid

### 2. Schedule from Grid
1. Click empty cell in Jennifer Walsh's row on Thursday
2. Modal opens with Jennifer pre-selected
3. Choose patient and PSW service
4. Assignment created

### 3. Edit Existing Assignment
1. Click the "SN Visit" block on Tuesday (David Lee)
2. Edit modal shows conflict warning
3. Can reassign to Michael Chen or adjust time
4. Can delete assignment

### 4. Deep Link Navigation
- `?staff_id=staff-2` - Shows only Sophia Rodriguez
- `?patient_id=patient-1` - Shows only Johnathan Smith's care needs
