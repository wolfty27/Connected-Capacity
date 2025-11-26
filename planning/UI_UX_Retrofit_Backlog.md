# UI/UX Retrofit Backlog

**Goal**: Align `connected-backup` React SPA with `UI/UX System Spec v1`.
**Execution Mode**: Agentic (Foreman/Worker).

---

## Phase 1: Foundation & Theming (Global)

### [UX-01] Implement Tailwind Design System
*   **Scope**: Update `tailwind.config.js` and `app.css`.
*   **Tasks**:
    *   Define Colors: `teal` (Primary), `indigo` (Secondary), `slate` (Neutral).
    *   Define Fonts: Add `Inter` family.
    *   Add Utilities: `.glass-nav`, `.card-shadow`, `.ai-glow`.
*   **Validation**: `bg-teal-900` renders the correct brand color.

### [UX-02] Switch to Light Theme Shell
*   **Scope**: `layouts/DashboardLayout.jsx`, `index.html`.
*   **Tasks**:
    *   Change global body bg to `bg-slate-50`.
    *   Update `DashboardLayout` to use White Sidebar and Light Content Area.
    *   Remove "Dark Mode" legacy styles.
*   **Validation**: App looks like a medical SaaS (Clean/White), not a developer tool (Dark).

---

## Phase 2: Core Components (The "Kit")

### [UX-03] Create "Premium" Card Component
*   **Scope**: `components/UI/Card.jsx`.
*   **Tasks**:
    *   Style: White bg, rounded-xl, shadow-sm, border-slate-200.
    *   Variants: `Standard`, `KPI` (with colored border), `Interactive` (hover lift).
*   **Validation**: Matches "Care Dashboard" widget look.

### [UX-04] Upgrade Data Table Component
*   **Scope**: `components/UI/DataTable.jsx`.
*   **Tasks**:
    *   Style headers (`uppercase text-xs text-slate-500`).
    *   Add support for "Status Badge" columns.
    *   Add "Action Menu" (three dots) support.
*   **Validation**: Can render the "Partner Performance" table from wireframes.

### [UX-05] Create Wizard Shell
*   **Scope**: `components/UI/Wizard.jsx`.
*   **Tasks**:
    *   Layout: Centered container (`max-w-3xl`).
    *   Props: `steps` (array), `currentStep` (int).
    *   UI: Stepper header (Circles + Labels), Bottom Action Bar (Back/Next).
*   **Validation**: Can render a dummy 3-step process.

---

## Phase 3: Navigation & Shell

### [UX-06] Implement Role-Based Sidebar
*   **Scope**: `components/Navigation/Sidebar.jsx`.
*   **Tasks**:
    *   Update styling to White/Teal.
    *   Implement Sections: "Care Ops", "Intake", "Network", "Admin".
    *   Add logic to show/hide based on `user.role` (SPO vs SSPO).
*   **Validation**: SPO_ADMIN sees "Intake"; FIELD_STAFF sees "My Worklist".

### [UX-07] Implement Top Navigation Bar
*   **Scope**: `components/Navigation/TopBar.jsx` (New).
*   **Tasks**:
    *   Layout: Sticky, Glass effect.
    *   Content: Breadcrumbs (Dynamic), Global Search Input, User Dropdown.
*   **Validation**: Matches "Care Dashboard" header.

---

## Phase 4: Feature Implementation (Dashboards)

### [UX-08] Build "Care Operations" Dashboard
*   **Scope**: `pages/CareOps/Dashboard.jsx`.
*   **Tasks**:
    *   Implement KPI Grid (Missed Care, Unfilled Shifts).
    *   Implement "Partner Performance" Table.
    *   Add "Gemini Forecast" Widget (UI only).
*   **Validation**: Matches `Care Dashboard (SPO Role).html`.

### [UX-09] Build "Referral Wizard" Page
*   **Scope**: `pages/Referrals/CreateReferral.jsx`.
*   **Tasks**:
    *   Use `Wizard` component.
    *   Step 1: Patient & Source.
    *   Step 2: Clinical Context (Urgency, Bundle).
    *   Step 3: Review.
    *   Add "AI Paste" button placeholder.
*   **Validation**: Matches `Referral Wizard.html`.

### [UX-10] Build "Transition Review" List
*   **Scope**: `pages/Tnp/ReviewList.jsx`.
*   **Tasks**:
    *   Use `DataTable`.
    *   Add "Acuity Score" visualization (Color bars).
    *   Add "Prioritize with Gemini" button.
*   **Validation**: Matches `Transition Review List.html`.

---

## Phase 5: Mobile (Field Staff)

### [UX-11] Mobile Responsiveness Check
*   **Scope**: Global.
*   **Tasks**:
    *   Ensure Sidebar collapses to Hamburger.
    *   Ensure Tables scroll horizontally or stack.
*   **Validation**: Usable on iPhone viewport.
