# Functional Spec: Scheduler 2.0 - AI-First Control Center

**Route:** `/spo/scheduling` (SPO), `/sspo/scheduling` (SSPO)  
**Main Component:** `SchedulingShell.jsx`  
**Version:** 2.0.0  
**Last Updated:** 2025-12-04

---

## Overview

Scheduler 2.0 is a complete redesign of the scheduling interface as an AI-first, multi-view control center. The shell provides tabbed navigation between four specialized views:

| Tab | Purpose | Primary Users |
|-----|---------|---------------|
| **AI Overview** | "Monday morning" briefing - AI summarizes the week | Coordinators, Managers |
| **Schedule** | Calendar view with Team Lanes grouping | Coordinators |
| **Review** | Batch review and accept AI proposals | Coordinators |
| **Conflicts** | Resolution center for scheduling issues | Coordinators, Managers |

---

## 1. Architecture

### 1.1 Component Structure

```
SchedulingShell.jsx
├── SchedulerContext.jsx (shared state)
│   ├── viewMode (ai-overview | schedule | review | conflicts)
│   ├── weekOffset (0 = current week)
│   ├── weekRange {start, end, startDate, endDate}
│   ├── filters {staffIds, patientIds, roleCodes, employmentTypeCodes}
│   ├── scheduleSubMode (team-lanes | list)
│   └── collapse states
│
├── Tabs
│   ├── AiOverviewTab.jsx
│   │   └── useAiOverviewData.js
│   │
│   ├── ScheduleTab.jsx
│   │   ├── useSchedulerData.js
│   │   └── TeamLaneGrid.jsx
│   │
│   ├── ReviewTab.jsx
│   │   └── useReviewData.js
│   │
│   └── ConflictsTab.jsx
│       └── useConflictsData.js
│
└── Shared Components
    ├── PatientTimeline.jsx
    ├── AssignCareServiceModal.jsx
    ├── EditAssignmentModal.jsx
    └── ExplanationModal.jsx
```

### 1.2 State Flow

```
URL params (staff_id, patient_id)
        ↓
SchedulerContext (shared state)
        ↓
    ┌───┴───┐
    │       │
useSchedulerData  useAiOverviewData  useReviewData  useConflictsData
    │       │            │                │
    ↓       ↓            ↓                ↓
ScheduleTab  AiOverviewTab  ReviewTab  ConflictsTab
```

---

## 2. UI States - Shell

### 2.1 Page Header

| Element | States |
|---------|--------|
| **Week Navigation** | `← Prev` • `Today` (hidden if current week) • `Next →` |
| **Week Range Display** | Format: `Dec 2, 2024 - Dec 8, 2024` |
| **Tab Bar** | 4 tabs with icons and optional badges |

### 2.2 Tab Navigation

| Tab | Icon | Badge Condition | Active Style |
|-----|------|-----------------|--------------|
| **AI Overview** | 💡 (LightBulbIcon) | None | `bg-blue-600 text-white shadow` |
| **Schedule** | 📅 (CalendarDaysIcon) | None | `bg-blue-600 text-white shadow` |
| **Review** | 📋 (ClipboardDocumentCheckIcon) | None | `bg-blue-600 text-white shadow` |
| **Conflicts** | ⚠️ (ExclamationTriangleIcon) | Count > 0 → red badge | `bg-blue-600 text-white shadow` |

**Inactive Tab Style:** `text-slate-700 hover:bg-slate-100`

### 2.3 Mode Variants

| Mode | Trigger | Visual Differences |
|------|---------|-------------------|
| **SPO Mode** | `isSppoMode === false` | Default styling |
| **SSPO Mode** | `isSppoMode === true` | May filter requirements by provider type |

---

## 3. AI Overview Tab

**Component:** `AiOverviewTab.jsx`  
**Data Hook:** `useAiOverviewData.js`

### 3.1 Loading State

| State | Visual |
|-------|--------|
| **Loading** | Centered spinner with "Loading AI insights..." text |
| **Error** | Red error banner with "Retry" button |
| **Loaded** | Full dashboard renders |

### 3.2 Quick Win Card

**Condition:** Displayed when `quickWin.count > 0`

| Element | Content |
|---------|---------|
| **Background** | Gradient `from-blue-50 to-cyan-50` with `border-blue-200` |
| **Title** | "Quick Win: Auto-Assign Safe Visits" |
| **Count** | `{quickWin.count} visits` with breakdown (strong + moderate) |
| **Time Saved** | `{estimatedMinutesSaved} minutes` estimate |
| **CTA Button** | "Review & Approve Assignments" → navigates to Review tab |

**Empty State:** Gray box with checkmark, "No Quick Wins Available"

### 3.3 AI Weekly Summary Card

**Condition:** Displayed when `weeklySummary !== null`

| Element | Content |
|---------|---------|
| **Background** | Gradient `from-violet-50 to-fuchsia-50` with `border-violet-200` |
| **Avatar** | 🤖 in violet circle |
| **Title** | "AI Weekly Summary" |
| **Source Badge** | "Powered by Gemini" if `source === 'vertex_ai'` |
| **Summary Text** | Natural language paragraph |
| **Highlights** | Bullet list with violet dots |
| **Priorities** | Numbered list with violet numbers |

### 3.4 Suggestion Summary Bar

**Condition:** Displayed when `suggestionCounts.total > 0`

| Match Status | Dot Color | Count |
|--------------|-----------|-------|
| Strong | `bg-emerald-500` | `{suggestionCounts.strong}` |
| Moderate | `bg-blue-500` | `{suggestionCounts.moderate}` |
| Weak | `bg-amber-500` | `{suggestionCounts.weak}` |
| No Match | `bg-red-500` | `{suggestionCounts.none}` |

### 3.5 Key Insights Grid

Three cards in responsive grid:

**1. Patients Requiring Attention**
| State | Content |
|-------|---------|
| Has items | List of patients with risk flags (High risk, Needs attention, No staff match) |
| Empty | Green checkmark, "All clear" |

**2. High-Priority Unscheduled**
| State | Content |
|-------|---------|
| Has items | List of services with remaining hours and visit counts |
| Empty | Green checkmark, "All scheduled" |

**3. Staff at Capacity**
| State | Content |
|-------|---------|
| Has items | List of staff with utilization bars (>85% highlighted) |
| Empty | Green checkmark, "Healthy utilization" |

### 3.6 Metrics Summary Cards

Four metric cards:

| Metric | Value Format | Status Colors |
|--------|--------------|---------------|
| **TFS** | `{value}h` | Band A/B/C/D colors |
| **Unscheduled** | `{hours}h` / `{patients} patients` | — |
| **Missed Care** | `{rate}%` | Band A/B/C/D colors |
| **Net Capacity** | `{hours}h` | Green/Amber/Red based on status |

---

## 4. Schedule Tab

**Component:** `ScheduleTab.jsx`  
**Data Hook:** `useSchedulerData.js`

### 4.1 Sub-Mode Toggle

| Mode | Icon | Description |
|------|------|-------------|
| **Team Lanes** (default) | UserGroupIcon | Staff grouped by role category |
| **List View** | ListBulletIcon | Sortable table of assignments |

### 4.2 Filters Bar

| Element | States |
|---------|--------|
| **Week Navigation** | Same as shell header (duplicated for convenience) |
| **Role Filter** | Multi-select from `/v2/workforce/metadata/roles` |
| **Employment Type Filter** | Multi-select from `/v2/workforce/metadata/employment-types` |
| **Clear Filters** | Visible when any filter active |
| **Staff/Patient Badges** | Blue/green pills when URL params present |

### 4.3 Quick Navigation Card (Collapsible)

| Panel | Inactive Style | Active Style | Content |
|-------|----------------|--------------|---------|
| **Staff-Centric** | `bg-blue-50 border-blue-200` | `bg-blue-100 border-blue-400` | Staff dropdown |
| **Patient-Centric** | `bg-green-50 border-green-200` | `bg-green-100 border-green-400` | Patient dropdown |
| **Full Dashboard** | `bg-slate-50 border-slate-200` | `bg-slate-100 border-slate-400` | Clear filters button |

### 4.4 Unscheduled Care Panel (Collapsible)

| State | Visual |
|-------|--------|
| **Expanded + Loading** | Spinner with "Generating suggestions..." |
| **Expanded + Has Suggestions** | Blue highlighted area with SuggestionRow components |
| **Expanded + No Suggestions** | "No AI suggestions generated. Click 'Auto Assign AI' to get started" |
| **Expanded + Requirements** | Patient cards with remaining care |
| **Collapsed** | Header only with summary stats |

**Auto Assign AI Button States:**

| State | Visual |
|-------|--------|
| Default | Blue button `bg-blue-600` with lightbulb icon |
| Loading | Disabled with spinner |
| Has Suggestions | Remains blue, count shown in panel |

### 4.5 Team Lane Grid

**Component:** `TeamLaneGrid.jsx`

#### Lane Grouping Logic

| Role Category | Lane Behavior | Example |
|---------------|---------------|---------|
| High population (>2 staff) | Individual lanes per staff | PSW, RPN |
| Low population (≤2 staff) | Combined lane per category | OT, PT, SLP |

#### Grid Structure

| Column | Content |
|--------|---------|
| **Staff Info (first col)** | Name, role code, employment type, utilization bar |
| **Day Columns (7 cols)** | Mon-Sun with assignments |

#### Staff Row States

| State | Visual |
|-------|--------|
| Highlighted (URL param) | `bg-blue-50` |
| Normal | `hover:bg-slate-50` |

#### Day Cell States

| State | Visual |
|-------|--------|
| Available, no assignments | Empty, clickable |
| Available, has assignments | Assignment chips |
| Weekend/unavailable | `bg-slate-100` gray background |

#### Utilization Bar Colors

| Utilization % | Color |
|---------------|-------|
| > 90% | `bg-red-500` |
| 75-90% | `bg-amber-500` |
| < 75% | `bg-emerald-500` |

#### Assignment Chip

| Property | Value |
|----------|-------|
| Background | Category-based color (see color map) |
| Content | Service type name, patient name, time range |
| Click Action | Opens Edit Assignment Modal |

### 4.6 List View Mode

Sortable table with columns:

| Column | Sortable | Click Action |
|--------|----------|--------------|
| Date | ✅ | Sort asc/desc |
| Time | ✅ | Sort asc/desc |
| Patient | ✅ | Sort asc/desc |
| Service | ✅ | Sort asc/desc |
| Staff | ✅ | Sort asc/desc |
| Status | ✅ | Sort asc/desc |

**Sort Indicator:** `▲` (ascending) or `▼` (descending)

**Status Badge Colors:**

| Status | Style |
|--------|-------|
| completed | `bg-emerald-100 text-emerald-700` |
| planned | `bg-blue-100 text-blue-700` |
| cancelled | `bg-slate-100 text-slate-500` |
| other | `bg-amber-100 text-amber-700` |

### 4.7 Patient Timeline

**Condition:** Rendered when `patientIdParam` is present (patient filter active)

Same functionality as v1, showing patient-centric daily timeline.

---

## 5. Review Tab

**Component:** `ReviewTab.jsx`  
**Data Hook:** `useReviewData.js`

### 5.1 Layout

Split two-column layout:
- **Left (1/3):** Proposal Groups list
- **Right (2/3):** Selected group details

### 5.2 Proposal Groups List

Groups are auto-generated from suggestions:

| Group Type | Title Example | Priority |
|------------|---------------|----------|
| `by_match_quality` | "High-Confidence Assignments" | 1 |
| `by_service_category` | "Nursing Services" | 2 |
| `by_patient` | "{Patient Name}'s Care Plan" | 3 |
| `by_match_quality` (weak) | "Requires Manual Review" | 4 |

#### Group Card

| Element | Content |
|---------|---------|
| Icon | Emoji based on type (⚡, 🏥, 👤, 📋) |
| Title | Group title |
| Count | `{count} suggestion(s)` |
| Badge | Strong match count if applicable |
| Source | "AI Action" or "Manual" |

#### Group Card States

| State | Style |
|-------|-------|
| Selected | `bg-blue-50 border-blue-300` |
| Unselected | `bg-white border-slate-200 hover:bg-slate-50` |

### 5.3 Selected Group Details

| Section | Content |
|---------|---------|
| **Header** | Title, description, type icon |
| **Metrics** | Grid of metric cards (strong count, moderate count, confidence, hours, etc.) |
| **Proposed Assignments Table** | Selectable rows with patient, service, staff, match badge |
| **Actions Footer** | Accept Selected / Accept All buttons |

#### Proposed Assignments Table

| Column | Content |
|--------|---------|
| Checkbox | Toggle selection |
| Patient | Patient name |
| Service | Service type name |
| Staff | Suggested staff name |
| Match | Confidence badge with percentage |
| Accept | Individual accept button |

#### Match Status Badge Colors

| Status | Style |
|--------|-------|
| strong | `text-emerald-600 bg-emerald-50` |
| moderate | `text-blue-600 bg-blue-50` |
| weak | `text-amber-600 bg-amber-50` |

#### Action Buttons

| Button | Condition | Action |
|--------|-----------|--------|
| Accept Selected | `selectedSuggestions.size > 0` | `handleAcceptSelected()` |
| Accept All | Always visible | `handleAcceptGroup()` |

**Button States:**

| State | Visual |
|-------|--------|
| Default | `bg-blue-600 text-white` |
| Loading | Spinner + disabled |
| Disabled | `opacity-50` |

### 5.4 Accept Result Toast

| Result | Style |
|--------|-------|
| All successful | `bg-emerald-50 border-emerald-200` |
| Some failed | `bg-amber-50 border-amber-200` |

### 5.5 Empty States

| Condition | Visual |
|-----------|--------|
| No groups | Checkmark, "All Caught Up!", "No pending AI proposals to review" |
| No group selected | Gray box, "Select a proposal group to view details" |

---

## 6. Conflicts Tab

**Component:** `ConflictsTab.jsx`  
**Data Hook:** `useConflictsData.js`

### 6.1 Layout

Split two-column layout:
- **Left (1/2):** Conflict list with filter tabs
- **Right (1/2):** Selected conflict details

### 6.2 Filter Tabs

| Filter | Count Display |
|--------|---------------|
| All | Total count |
| No Match | Count of `type === 'no_match'` |
| Double Booked | Count of `type === 'double_booked'` |
| Travel | Count of `type === 'travel'` |
| Capacity | Count of `type === 'capacity'` |

**Tab States:**

| State | Style |
|-------|-------|
| Selected | `bg-blue-50 border-blue-300 text-blue-700` |
| Unselected | `bg-white border-slate-200 text-slate-600 hover:bg-slate-50` |

### 6.3 Conflict List

#### Conflict Card

| Element | Content |
|---------|---------|
| Left Border | Severity color (red/amber/blue) |
| Title | Patient name or staff name |
| Subtitle | Service type, date |
| Badge | Type badge (No Match, Double Booked, Travel, Capacity) |

#### Type Badge Colors

| Type | Style |
|------|-------|
| no_match | `bg-red-100 text-red-700` |
| double_booked | `bg-amber-100 text-amber-700` |
| travel | `bg-blue-100 text-blue-700` |
| capacity | `bg-purple-100 text-purple-700` |
| spacing | `bg-indigo-100 text-indigo-700` |

#### Severity Border Colors

| Severity | Border Color |
|----------|--------------|
| high | `border-l-red-500` |
| medium | `border-l-amber-500` |
| low | `border-l-blue-500` |

### 6.4 Selected Conflict Details

| Section | Content |
|---------|---------|
| **Header** | Type badge, severity indicator, patient/staff name |
| **Issue Details** | AI explanation box with warning icon |
| **Additional Details** | Type-specific content (conflicting assignments, utilization bar, etc.) |
| **Suggested Resolutions** | List of resolution options |
| **Actions** | Dismiss / Open in Schedule buttons |

#### Type-Specific Details

**Double Booked:**
- Shows two conflicting assignment cards with patient names and times

**Capacity:**
- Shows utilization progress bar with percentage
- Color-coded: `bg-red-500` (>120%) or `bg-amber-500` (100-120%)

### 6.5 Resolution Options

Dynamically generated based on conflict type:

| Type | Resolution Options |
|------|-------------------|
| no_match | Adjust Time Window, Expand Search Radius, Escalate to SSPO |
| double_booked | Reschedule Earlier Visit, Reschedule Later Visit, Assign Different Staff |
| travel | Adjust Appointment Time, Optimize Route |
| capacity | Redistribute Workload, Approve Overtime |

Each resolution shows:
- Label
- Description
- "Apply Resolution" button (navigates to Schedule tab)

### 6.6 Empty States

| Condition | Visual |
|-----------|--------|
| No conflicts | Green checkmark, "No Conflicts!", "All scheduling constraints are satisfied." |
| No conflicts of type | "No {type} conflicts found." |
| No conflict selected | Gray box, "Select a conflict to view details and resolution options" |

---

## 7. Shared Modals

### 7.1 Assign Care Service Modal

Same as v1 with additions:
- Pre-population from AI suggestions when accepting

### 7.2 Edit Assignment Modal

Same as v1.

### 7.3 Explanation Modal

Same as v1 with enhanced confidence labels.

---

## 8. Data Flow & APIs

### 8.1 Data Hooks Summary

| Hook | Primary APIs | Refresh Triggers |
|------|--------------|------------------|
| `useSchedulerData` | `/v2/scheduling/grid`, `/v2/scheduling/requirements`, metadata endpoints | Week change, filter change, CRUD |
| `useAiOverviewData` | `/v2/tfs/summary`, `/v2/spo-dashboard`, `/v2/workforce/capacity`, `/v2/scheduling/suggestions`, `/v2/scheduling/suggestions/weekly-summary` | Week change, manual refresh |
| `useReviewData` | `/v2/scheduling/suggestions` | Week change, accept actions |
| `useConflictsData` | `/v2/scheduling/suggestions`, `/v2/scheduling/grid` | Week change, CRUD |

### 8.2 API Endpoints

**Core Scheduling:**

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v2/scheduling/grid` | Staff and assignments for week |
| GET | `/v2/scheduling/requirements` | Unscheduled care requirements |
| POST | `/v2/scheduling/assignments` | Create assignment |
| PUT | `/v2/scheduling/assignments/{id}` | Update assignment |
| POST | `/v2/scheduling/assignments/{id}/cancel` | Cancel assignment |
| GET | `/v2/scheduling/eligible-staff` | Staff eligible for service/date/time |

**AI Auto-Assign:**

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v2/scheduling/suggestions` | Generate AI suggestions |
| GET | `/v2/scheduling/suggestions/summary` | Summary stats |
| GET | `/v2/scheduling/suggestions/weekly-summary` | AI weekly brief |
| GET | `/v2/scheduling/suggestions/{pid}/{stid}/explain` | Explain match |
| POST | `/v2/scheduling/suggestions/accept` | Accept single |
| POST | `/v2/scheduling/suggestions/accept-batch` | Accept batch |
| GET | `/v2/scheduling/suggestions/analytics` | Learning loop stats |

**Metadata:**

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v2/workforce/metadata/roles` | Staff roles |
| GET | `/v2/workforce/metadata/employment-types` | Employment types |
| GET | `/v2/workforce/metadata/team-lanes` | Team lane config |

**Metrics:**

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v2/tfs/summary` | Time-to-First-Service |
| GET | `/v2/spo-dashboard` | Dashboard KPIs |
| GET | `/v2/workforce/capacity` | Capacity vs required |

### 8.3 URL Parameters

| Param | Effect |
|-------|--------|
| `staff_id` | Filters to single staff, highlights in grid |
| `patient_id` | Switches to PatientTimeline view |

### 8.4 Data Constraints

| Constraint | Value |
|------------|-------|
| Staff dropdown limit | 1000 |
| Patient dropdown limit | 1000 |
| Services per patient card | 3 max (shows "+N more") |
| Batch accept limit | 50 suggestions |
| Duration options | 30, 45, 60, 90, 120, 180, 240 min |

---

## 9. Business Logic

### 9.1 AI Suggestion Generation

Suggestions are grouped into proposal groups:

1. **High-Confidence** - Strong + moderate matches
2. **By Service Category** - When 3+ suggestions of same category
3. **By Patient** - When 2+ suggestions for same patient
4. **Requires Review** - Weak matches only

### 9.2 Conflict Detection

| Type | Detection Logic |
|------|-----------------|
| `no_match` | `match_status === 'none'` from suggestions |
| `double_booked` | Overlapping `start_time` and `end_time` for same staff |
| `travel` | Gap < 15 minutes between consecutive visits |
| `capacity` | Staff `utilization > 100%` |

### 9.3 Team Lane Grouping

| Condition | Lane Behavior |
|-----------|---------------|
| Role has >2 staff | Individual lanes per staff member |
| Role has ≤2 staff | Combined lane showing all staff |

Lane groups are based on `role.category`:
- Nursing
- Allied Health
- Personal Support
- Administrative
- Community Support

### 9.4 Server-Side Validation

All assignment operations validate:
1. Patient non-concurrency
2. PSW spacing rules (120 min gap)
3. Staff role eligibility
4. Staff availability
5. Staff capacity

### 9.5 Learning Loop

All AI suggestions are logged:

| Event | Logged Data |
|-------|-------------|
| Generation | patient_id, service_type_id, match_status, confidence_score |
| Accept | outcome='accepted', assignment_id, time_to_decision |
| Modify | outcome='modified', modifications JSON |
| Reject | outcome='rejected', rejection_reason |

---

## 10. Color Reference

### Service Category Colors

| Category | Background |
|----------|------------|
| nursing | `#DBEAFE` (blue-100) |
| personal_care, psw | `#D1FAE5` (emerald-100) |
| therapy, rehab | `#FEF3C7` (amber-100) |
| default | `#E5E7EB` (slate-200) |

### Match Status Colors

| Status | Badge Style |
|--------|-------------|
| strong | `bg-emerald-100 text-emerald-800` |
| moderate | `bg-blue-100 text-blue-800` |
| weak | `bg-amber-100 text-amber-800` |
| none | `bg-red-100 text-red-800` |

### Severity Colors

| Severity | Border / Indicator |
|----------|-------------------|
| high | `red-500` |
| medium | `amber-500` |
| low | `blue-500` |

### Utilization Colors

| Range | Color |
|-------|-------|
| > 90% | `red-500` |
| 75-90% | `amber-500` |
| < 75% | `emerald-500` |

---

## 11. Summary Stats

| Metric | Count |
|--------|-------|
| **Total API endpoints** | 17 |
| **Tabs** | 4 |
| **Data hooks** | 4 |
| **Modals** | 3 |
| **Collapsible sections** | 3 |
| **View modes** | 3 (Team Lanes, List, Patient Timeline) |
| **Conflict types** | 4 |
| **Proposal group types** | 4 |
| **Filter mechanisms** | 4 |
| **Service categories** | 5 |
| **Match status levels** | 4 |

---

## 12. Future Considerations

Items designed but not yet implemented:

1. **Accept-All Flow** - One-click accept for entire week's high-confidence suggestions
2. **Drag-and-Drop** - Reassign by dragging assignment chips
3. **Keyboard Shortcuts** - `Ctrl+S` save, `Esc` close modal, etc.
4. **Scenario Planning** - "What-if" simulation mode
5. **Real-time Updates** - WebSocket push for multi-user collaboration

