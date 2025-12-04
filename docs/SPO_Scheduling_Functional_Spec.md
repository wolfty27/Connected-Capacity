# Functional Spec for Design: SPO Scheduling Dashboard

**Route:** `/spo/scheduling`  
**Component:** `SchedulingPage.jsx`  
**Mode:** Can render as SPO (default) or SSPO (`isSspoMode=true` prop)

---

## 1. UI States (Visual Variations)

### 1.1 Page-Level States

| State | Trigger | Visual |
|-------|---------|--------|
| **Loading** | Initial page load, `loading === true` | Full-screen centered `<Spinner size="lg" />`, no other content visible |
| **Loaded (Default)** | Data fetched successfully | Full UI renders: Header, Filters, Quick Nav, Unscheduled Panel, Schedule Grid |
| **SPO Mode** | `isSspoMode === false` (default) | White header (`bg-white`), title "Scheduler", subtitle "Manage, view, and assign care appointments" |
| **SSPO Mode** | `isSspoMode === true` | Purple header (`bg-purple-50`), title "SSPO Scheduler", subtitle "Nursing, Allied Health & Specialized Services" |

### 1.2 Week Navigation Buttons

| Element | States |
|---------|--------|
| **← Prev Button** | Always enabled. `variant="secondary"`, `size="sm"` |
| **Next → Button** | Always enabled. `variant="secondary"`, `size="sm"` |
| **Today Button** | **Hidden** when `weekOffset === 0`. **Visible** when `weekOffset !== 0`. `variant="ghost"`, `size="sm"` |

### 1.3 Filter Bar

| Element | States |
|---------|--------|
| **Role Dropdown** | Default: "All Roles". Options populated from `/v2/workforce/metadata/roles` API. Format: `{code} - {name}` |
| **Employment Type Dropdown** | Default: "All Employment Types". Options from `/v2/workforce/metadata/employment-types` |
| **Clear Filters Button** | **Hidden** when `!hasFilters`. **Visible** when any filter active (`staffIdParam \|\| patientIdParam \|\| roleFilter \|\| empTypeFilter`). `variant="ghost"` |
| **Staff Filter Badge** | **Visible only** when `staffIdParam` present. Blue pill (`bg-blue-100 text-blue-700`). Shows staff name or fallback `ID {staffIdParam}` |
| **Patient Filter Badge** | **Visible only** when `patientIdParam` present. Green pill (`bg-green-100 text-green-700`). Shows patient name or fallback `ID {patientIdParam}` |

### 1.4 Quick Navigation Card (Collapsible)

| State | Trigger | Visual |
|-------|---------|--------|
| **Expanded** | `isNavCollapsed === false` (default) | Shows 3 navigation panels in a grid |
| **Collapsed** | `isNavCollapsed === true` | Only header bar visible, chevron points down |

**Staff-Centric Panel States:**
- **Inactive:** `bg-blue-50 border-blue-200`
- **Active:** `bg-blue-100 border-blue-400` (when `staffIdParam` present)

**Patient-Centric Panel States:**
- **Inactive:** `bg-green-50 border-green-200`
- **Active:** `bg-green-100 border-green-400` (when `patientIdParam` present)

**Full Dashboard Panel States:**
- **Inactive:** `bg-slate-50 border-slate-200`, button shows "Clear filters & view all"
- **Active:** `bg-slate-100 border-slate-400`, button shows "Viewing all schedules" (disabled)

### 1.5 Unscheduled Care Panel (Collapsible)

| State | Visual |
|-------|--------|
| **Expanded + Has Data** | Amber header (`bg-amber-50`), horizontal scrollable cards showing patients with unscheduled services |
| **Expanded + Empty** | Green checkmark icon, text "All required care scheduled for this week" |
| **Collapsed** | Only header visible with summary stats, chevron points down |

**Header Stats (always visible):**
- `{X} patients need scheduling`
- `({Y}h + {Z} visits remaining)` - only if `total_remaining_hours > 0`

**Auto Assign Button States:**

| State | Condition | Visual |
|-------|-----------|--------|
| **Hidden** | Panel collapsed OR no requirements data | Not rendered |
| **Default** | No suggestions generated | Blue button (`bg-blue-600`), text "⚡ Auto Assign" |
| **Loading** | `autoAssignLoading === true` | Disabled, shows spinner animation |
| **Has Suggestions** | `hasSuggestions === true` | Green button (`bg-emerald-100 text-emerald-700`), text "⚡ {N} Suggestions" |

**Clear Suggestions Button (X):**
- **Visible only** when `hasSuggestions === true`

### 1.6 Patient Unscheduled Card States

Each card shows max **3 services**. If more exist: "+{N} more services" text.

**Risk Flags (in card header):**
- `risk_flags.includes('dangerous')` → Red exclamation `!`
- `risk_flags.includes('warning')` → Amber exclamation `!`

### 1.7 AI Suggestion Row States (under each service)

| Match Status | Background | Border | Badge | Icon |
|--------------|------------|--------|-------|------|
| **strong** | `bg-emerald-50` | `border-emerald-200` | `bg-emerald-100 text-emerald-800` | ⚡ |
| **moderate** | `bg-blue-50` | `border-blue-200` | `bg-blue-100 text-blue-800` | 👍 |
| **weak** | `bg-amber-50` | `border-amber-200` | `bg-amber-100 text-amber-800` | 🤔 |
| **none** | Not rendered (hidden) | — | — | — |

**Accept Button States:**
- **Default:** Green button (`bg-emerald-600`), checkmark icon + "Accept"
- **Loading:** `isAccepting === true` for this suggestion → disabled, spinner animation

### 1.8 Schedule Grid (Staff View)

**Rendered when:** `!patientIdParam` (no patient filter)

**Staff Row Highlighting:**
- `staffIdParam` matches row → `bg-blue-50`
- Default → `hover:bg-slate-50`

**Utilization Bar Colors:**

| Utilization | Color |
|-------------|-------|
| >90% | `bg-red-500` |
| 75-90% | `bg-amber-500` |
| <75% | `bg-emerald-500` |

**Day Cell States:**

| State | Visual |
|-------|--------|
| Staff unavailable on this day | `bg-slate-100` (gray background) |
| Available, no assignments | Clickable (opens Assign Modal) |
| Available, has assignments | Shows assignment chips |

**Assignment Chip Colors (by category):**

| Category | Background Color |
|----------|------------------|
| nursing | `#DBEAFE` |
| psw, personal_support | `#D1FAE5` |
| homemaking | `#FEF3C7` |
| behaviour, behavioral | `#FEE2E2` |
| rehab, therapy | `#E9D5FF` |
| default | `#F3F4F6` |

**Empty Grid State:**
- "No staff match the current filters" centered text

### 1.9 Patient Timeline (Patient View)

**Rendered when:** `patientIdParam` is present

**Day Row States:**
- **Today:** Ring highlight (`ring-2 ring-teal-400`), header `bg-teal-50`, "Today" badge
- **Other days:** Standard `bg-slate-100` header

**Assignment Card Status Badges:**

| Status | Verification | Badge Style |
|--------|--------------|-------------|
| completed + VERIFIED | — | `bg-emerald-100 text-emerald-700` "Verified" |
| completed + MISSED | — | `bg-red-100 text-red-700` "Missed" |
| completed | — | `bg-emerald-100 text-emerald-700` "Completed" |
| planned | — | `bg-blue-100 text-blue-700` "Scheduled" |
| pending | — | `bg-blue-100 text-blue-700` "Pending" |
| in_progress | — | `bg-amber-100 text-amber-700` "In Progress" |
| cancelled | — | `bg-slate-100 text-slate-500` "Cancelled" |

**Empty Day State:** "No visits scheduled" centered text

### 1.10 Modals

#### Assign Care Service Modal

| Element | States |
|---------|--------|
| **Patient Select** | Required. Pre-populated if opened from Unscheduled panel |
| **Service Type Select** | Required. Pre-populated if opened from Unscheduled panel |
| **Date Input** | Required. Default: `weekRange.start` |
| **Time Input** | Required. Default: `09:00` |
| **Duration Select** | Options: 30, 45, 60, 90, 120, 180, 240 minutes. Default: 60 |
| **Staff Select** | Required. **Dynamically populated** based on service type + date + time |
| **Staff Empty Warning** | Shown when service selected but `eligibleStaff.length === 0` |
| **Notes Textarea** | Optional |
| **Submit Button** | Disabled during `loading`. Text: "Creating..." when loading |

#### Edit Assignment Modal

| Element | States |
|---------|--------|
| **Header** | Shows service name + patient name |
| **Date/Time/Duration** | Editable |
| **Notes** | Editable |
| **Cancel Assignment Button** | `variant="danger"`, triggers confirm dialog |
| **Save Changes Button** | Disabled during `loading`. Text: "Saving..." when loading |

#### Explanation Modal (AI)

| State | Visual |
|-------|--------|
| **Loading** | Spinner + "Generating explanation..." |
| **Error** | Warning icon, error message, "Try again" link |
| **Success** | Confidence badge, short explanation, detailed points list, optional score breakdown |

**Confidence Badge Colors:**

| Label | Style |
|-------|-------|
| High Match | `bg-emerald-100 text-emerald-800` |
| Good Match | `bg-blue-100 text-blue-800` |
| Acceptable | `bg-amber-100 text-amber-800` |
| Limited Options | `bg-orange-100 text-orange-800` |
| No Match | `bg-red-100 text-red-800` |

**Source Indicator:**
- `vertex_ai` → 🤖 "AI Generated"
- `rules_based` → 📋 "Rules-Based"

---

## 2. User Interactions & Logic

### 2.1 Click Map

| Element | Action | Function/Effect |
|---------|--------|-----------------|
| **← Prev Week** | Click | `setWeekOffset(prev => prev - 1)` → Refetches all data |
| **Next Week →** | Click | `setWeekOffset(prev => prev + 1)` → Refetches all data |
| **Today** | Click | `setWeekOffset(0)` → Returns to current week |
| **Role Filter** | Change | `setRoleFilter(e.target.value)` → Refetches grid data |
| **Emp Type Filter** | Change | `setEmpTypeFilter(e.target.value)` → Refetches grid data |
| **Clear Filters** | Click | `clearFilters()` → Resets all filters, navigates to base URL |
| **Staff Select (Quick Nav)** | Change | `setSearchParams({ staff_id: value })` → URL change triggers refetch |
| **Patient Select (Quick Nav)** | Change | `setSearchParams({ patient_id: value })` → URL change triggers refetch |
| **Full Dashboard Button** | Click | `clearFilters()` → Only if `hasFilters` |
| **Quick Nav Collapse Toggle** | Click | `setIsNavCollapsed(!isNavCollapsed)` |
| **Unscheduled Panel Collapse** | Click | `setIsUnscheduledCollapsed(!isUnscheduledCollapsed)` |
| **⚡ Auto Assign Button** | Click | `handleAutoAssign()` → Calls `generateSuggestions()` API |
| **X Clear Suggestions** | Click | `clearSuggestions()` → Clears local state |
| **Assign Button (per service)** | Click | `openAssignModal(patient, serviceTypeId)` |
| **💡 Explain Button (suggestion)** | Click | `handleOpenExplanation(suggestion)` → Opens modal |
| **✏️ Manual Button (suggestion)** | Click | `openAssignModal(item, serviceTypeId)` |
| **✓ Accept Button (suggestion)** | Click | `handleAcceptSuggestion(suggestion)` → Creates assignment at 09:00 |
| **Grid Day Cell (empty)** | Click | `openAssignModal(null, null, staff, dateString)` |
| **Assignment Chip (grid)** | Click | `openEditModal(assignment)` |
| **Timeline Card** | Click | `onEditAssignment(assignment)` → Opens edit modal |
| **Modal Backdrop** | Click | Closes modal |
| **Modal Close (X)** | Click | Closes modal |
| **Cancel Assignment** | Click | `confirm()` dialog → `handleCancelAssignment(id)` → DELETE API |

### 2.2 Immediate vs Async Effects

| Action | Immediate (Optimistic) | After API |
|--------|------------------------|-----------|
| Accept Suggestion | Sets `acceptingId`, shows spinner | Removes suggestion from list, refetches grid + requirements |
| Create Assignment | Sets modal `loading=true` | Closes modal, refetches grid + requirements |
| Update Assignment | Sets modal `loading=true` | Closes modal, refetches grid |
| Cancel Assignment | Confirm dialog | Closes modal, refetches grid + requirements |
| Generate Suggestions | Sets `autoAssignLoading=true` | Populates `suggestions` array |
| Filter Change | Immediate state update | Refetches grid data |
| Week Change | Immediate state update | Refetches all 3 data sources |

### 2.3 Hidden Gestures

**None.** All interactions are explicit clicks/changes. No:
- Long press
- Drag and drop
- Swipe gestures
- Keyboard shortcuts

---

## 3. Data Flow & Constraints

### 3.1 Data Sources

| Data | Source | Endpoint | Refresh Trigger |
|------|--------|----------|-----------------|
| Grid Data (staff + assignments) | API | `GET /v2/scheduling/grid` | Week change, filter change, assignment CRUD |
| Requirements (unscheduled care) | API | `GET /v2/scheduling/requirements` | Week change, patient filter, assignment CRUD |
| Roles Metadata | API | `GET /v2/workforce/metadata/roles` | Initial load only |
| Employment Types | API | `GET /v2/workforce/metadata/employment-types` | Initial load only |
| Navigation Examples | API | `GET /v2/scheduling/navigation-examples` | Initial load, staff/patient filter change |
| All Staff | API | `GET /v2/workforce/staff?status=active&limit=100` | Initial load only |
| All Patients | API | `GET /patients?status=Active&limit=100` | Initial load only |
| Suggestions | API | `GET /v2/scheduling/suggestions` | Manual trigger only |
| Explanation | API | `GET /v2/scheduling/suggestions/{pid}/{stid}/explain` | On-demand, cached |
| Eligible Staff | API | `GET /v2/scheduling/eligible-staff` | Service type + date + time change in modal |

### 3.2 URL Parameters (Deep Links)

| Param | Effect |
|-------|--------|
| `staff_id` | Filters grid to single staff, highlights row, activates Staff-Centric panel |
| `patient_id` | Switches from Grid to PatientTimeline view, filters requirements, activates Patient-Centric panel |

### 3.3 Data Constraints & Limits

| Constraint | Value | Location |
|------------|-------|----------|
| Staff dropdown limit | 100 | `/v2/workforce/staff?limit=100` |
| Patient dropdown limit | 100 | `/patients?limit=100` |
| Services shown per patient card | 3 max | `item.services?.slice(0, 3)` - shows "+N more" if exceeded |
| Duration options | 30, 45, 60, 90, 120, 180, 240 min | Hard-coded in modal |
| Duration min/max (API validation) | 15–480 minutes | Server-side validation |
| Notes max length | 1000 chars | Server-side validation |
| Batch accept limit | 50 suggestions | API constraint |
| Week calculation | ISO week (Mon–Sun) | `dayOfWeek === 0 ? 6 : dayOfWeek - 1` |

### 3.4 Text Truncation

| Element | Behavior |
|---------|----------|
| Patient name in card | No explicit truncation |
| Staff name in grid | No explicit truncation |
| Service type name (grid chip) | `truncate` class |
| Patient name (grid chip) | No truncation |
| Staff name (suggestion row) | `truncate` class |

---

## 4. Critical Business Logic

### 4.1 Invisible Rules

| Rule | Implementation | Design Impact |
|------|----------------|---------------|
| **SPO sees ALL services** | `provider_type` param omitted for SPO mode | SPO dashboard never filters by provider |
| **SSPO sees only SSPO services** | `provider_type: 'sspo'` sent in requirements API | SSPO dashboard shows reduced service list |
| **Staff availability by day** | `staff.availability?.some(a => a.day_of_week === dayOfWeek)` | Unavailable days render as gray (`bg-slate-100`) |
| **Cancelled assignments hidden** | `status !== 'cancelled'` filter | Grid cells never show cancelled visits |
| **Accept creates 09:00 slot** | `weekRange.start + 'T09:00:00'` | All auto-accepted suggestions default to 9 AM Monday |
| **Eligible staff dynamically fetched** | On service type + date + time change | Staff dropdown in modal may show 0 options |
| **Explanation caching** | `explanations[cacheKey]` in hook | Same suggestion won't re-fetch explanation |
| **Assignment validation server-side** | `SchedulingEngine::validateAssignment()` | API may reject with 422 + validation errors |

### 4.2 Validation Checks (Server-Side)

The API validates before creating/updating assignments:
1. **Patient non-concurrency** - Patient can't have overlapping visits
2. **PSW spacing rules** - Minimum gap between visits of same service type
3. **Staff role eligibility** - Staff must have eligible role for service
4. **Staff capacity** - Staff must have remaining hours
5. **Staff availability** - Staff must be scheduled to work that day

Validation failures return:
```json
{
  "message": "Assignment validation failed",
  "errors": ["Patient already has a visit at this time"],
  "warnings": []
}
```

### 4.3 Mode-Specific Behavior

| Feature | SPO Mode | SSPO Mode |
|---------|----------|-----------|
| Header color | White | Purple |
| Title | "Scheduler" | "SSPO Scheduler" |
| Requirements filter | None (sees all) | `provider_type: 'sspo'` |
| Service visibility | All service types | Only SSPO-owned (nursing, allied health, etc.) |

### 4.4 State Persistence

| State | Persisted? | Method |
|-------|------------|--------|
| Week offset | No | Resets to 0 on page load |
| Role/Emp filters | No | Resets on page load |
| Staff/Patient filters | Yes | URL params |
| Collapse states | No | Defaults to expanded |
| Suggestions | No | Must regenerate each session |

### 4.5 Error Handling

| Error Case | UI Response |
|------------|-------------|
| API fetch failure | `console.error` logged, no user notification |
| Assignment creation failure | `alert()` with error message |
| Assignment update failure | `alert()` with error message |
| Suggestion generation failure | `console.error` logged, button remains in default state |
| Suggestion accept failure | `alert()` with error message |
| Explanation fetch failure | Error state in modal with "Try again" option |

---

## Summary Stats

- **5 API endpoints** for main data
- **6 API endpoints** for AI auto-assign
- **3 modals** (Assign, Edit, Explanation)
- **2 collapsible sections** (Quick Nav, Unscheduled)
- **2 main views** (Staff Grid, Patient Timeline)
- **4 filter mechanisms** (Role, Emp Type, Staff ID, Patient ID)
- **7 service categories** with distinct colors
- **4 match status levels** for AI suggestions

